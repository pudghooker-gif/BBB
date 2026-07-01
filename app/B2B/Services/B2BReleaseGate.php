<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;
use VanguardLTE\Support\Validation\SecurityHardenedValidator;

class B2BReleaseGate
{
    public function run($production = false, $checkFiles = true, $checkDependencyAudit = false)
    {
        $checks = [
            $this->cacheStoreCheck('nonce_cache', config('b2b.nonce_cache_store') ?: config('cache.default'), $production),
            $this->cacheStoreCheck('rate_limit_cache', config('b2b.rate_limit_cache_store') ?: config('cache.default'), $production),
            $this->queueCheck($production),
            $this->booleanCheck('app_debug', !(bool) config('app.debug'), $production, 'APP_DEBUG must be false for production.'),
            $this->booleanCheck('private_wallet_callbacks', !(bool) config('b2b.allow_private_wallet_callbacks'), $production, 'Private wallet callback targets must stay disabled in production.'),
            $this->booleanCheck('sandbox_disabled', !(bool) config('b2b.sandbox_enabled'), $production, 'B2B sandbox must be disabled in production.'),
            $this->deploymentArtifactsCheck($production),
            $this->websocketRuntimeCheck($production),
            $this->adminRbacCheck($production),
            $this->webSurfacesCheck($production),
            $this->laravelSecurityMitigationsCheck($production),
        ];

        if ($checkFiles) {
            $checks[] = $this->secretFilesCheck($production);
        }

        if ($checkDependencyAudit) {
            $checks[] = $this->dependencyAuditCheck($production);
        }

        $ok = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'checks' => $checks,
        ];
    }

    private function cacheStoreCheck($name, $store, $production)
    {
        $driver = config('cache.stores.' . $store . '.driver');
        $ok = !$production || $driver === 'redis';

        return [
            'name' => $name,
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok
                ? 'Cache store is acceptable: ' . ($store ?: 'default') . ' (' . ($driver ?: 'unknown') . ').'
                : 'Production B2B shared state must use Redis. Current store: ' . ($store ?: 'default') . ' (' . ($driver ?: 'unknown') . ').',
        ];
    }

    private function queueCheck($production)
    {
        $connection = config('queue.default');
        $driver = config('queue.connections.' . $connection . '.driver');
        $ok = !$production || $driver === 'redis';

        return [
            'name' => 'queue_driver',
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok
                ? 'Queue driver is acceptable: ' . ($connection ?: 'default') . ' (' . ($driver ?: 'unknown') . ').'
                : 'Production B2B workers must use Redis queues. Current queue: ' . ($connection ?: 'default') . ' (' . ($driver ?: 'unknown') . ').',
        ];
    }

    private function booleanCheck($name, $condition, $production, $message)
    {
        return [
            'name' => $name,
            'status' => (!$production || $condition) ? 'pass' : 'fail',
            'message' => (!$production || $condition) ? 'Configuration is acceptable.' : $message,
        ];
    }

    private function secretFilesCheck($production)
    {
        $paths = [
            '.env',
            '.env_old',
            'totalbet365.sql',
            'PTWebSocket/ssl/key.key',
            'PTWebSocket/ssl/crt.crt',
        ];

        $present = [];
        foreach ($paths as $path) {
            if (file_exists(base_path($path))) {
                $present[] = $path;
            }
        }

        return [
            'name' => 'release_secret_files',
            'status' => count($present) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($present) > 0
                ? 'Secret-bearing/local files must be excluded from production artifacts: ' . implode(', ', $present)
                : 'No known secret-bearing/local release blocker files were found.',
        ];
    }

    private function deploymentArtifactsCheck($production)
    {
        $paths = [
            'deploy/nginx/bbb-b2b.conf.example',
            'deploy/php-fpm/bbb-b2b.pool.conf.example',
            'deploy/supervisor/b2b-workers.conf.example',
            'deploy/systemd/bbb-scheduler.service',
            'deploy/systemd/bbb-scheduler.timer',
            'deploy/systemd/bbb-websocket.service',
            'deploy/cron/bbb-maintenance.cron.example',
            'deploy/scripts/backup.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
        ];

        $missing = [];
        foreach ($paths as $path) {
            if (!file_exists(base_path($path))) {
                $missing[] = $path;
            }
        }

        return [
            'name' => 'deployment_artifacts',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Production deployment templates and runbook are present.'
                : 'Missing production deployment artifacts: ' . implode(', ', $missing),
        ];
    }

    private function websocketRuntimeCheck($production)
    {
        $missing = [];
        $paths = [
            'PTWebSocket/Server.js',
            'PTWebSocket/package.json',
            'PTWebSocket/pnpm-lock.yaml',
            'deploy/websocket/socket_config2.production.example.json',
            'deploy/systemd/bbb-websocket.service',
        ];

        foreach ($paths as $path) {
            if (!file_exists(base_path($path))) {
                $missing[] = 'path:' . $path;
            }
        }

        $package = $this->jsonFile(base_path('PTWebSocket/package.json'));
        if (!$package) {
            $missing[] = 'json:PTWebSocket/package.json';
        } else {
            foreach (['ws', 'request', 'mysql2', 'ioredis', 'moment-timezone'] as $dependency) {
                if (empty($package['dependencies'][$dependency])) {
                    $missing[] = 'package_dependency:' . $dependency;
                }
            }

            if (empty($package['scripts']['start']) || strpos($package['scripts']['start'], 'node Server.js') === false) {
                $missing[] = 'package_script:start';
            }

            if (empty($package['scripts']['check:syntax'])) {
                $missing[] = 'package_script:check:syntax';
            }
        }

        $server = $this->fileContents(base_path('PTWebSocket/Server.js'));
        foreach (['serverConfig.listen_port', 'serverConfig.listen_host', '../public/socket_config2.json', 'new WebSocket.Server'] as $needle) {
            if (strpos($server, $needle) === false) {
                $missing[] = 'server_js:' . $needle;
            }
        }

        $nginx = $this->fileContents(base_path('deploy/nginx/bbb-b2b.conf.example'));
        foreach (['bbb_b2b_websocket', 'listen 12096 ssl', 'proxy_set_header Upgrade', 'proxy_buffering off'] as $needle) {
            if (strpos($nginx, $needle) === false) {
                $missing[] = 'nginx_websocket:' . $needle;
            }
        }

        $healthcheck = $this->fileContents(base_path('deploy/scripts/healthcheck.sh'));
        foreach (['WEBSOCKET_TCP_HOST', 'WEBSOCKET_TCP_PORT', '/dev/tcp'] as $needle) {
            if (strpos($healthcheck, $needle) === false) {
                $missing[] = 'healthcheck_websocket:' . $needle;
            }
        }

        return [
            'name' => 'websocket_runtime',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Node/WebSocket manifest, lockfile, proxy template, and health probe are present.'
                : 'Missing Node/WebSocket runtime release coverage: ' . implode(', ', $missing),
        ];
    }

    private function adminRbacCheck($production)
    {
        $requiredPermissions = [
            'b2b.operators.create',
            'b2b.credentials.rotate',
            'b2b.credentials.revoke',
            'b2b.wallet.manual_action',
            'b2b.audit.view',
        ];
        $requiredActions = [
            'operator.create' => 'b2b.operators.create',
            'api_key.rotate' => 'b2b.credentials.rotate',
            'api_key.revoke' => 'b2b.credentials.revoke',
            'wallet.manual_action' => 'b2b.wallet.manual_action',
        ];

        $permissions = config('b2b_admin.permissions', []);
        $actions = config('b2b_admin.privileged_actions', []);
        $missing = [];

        foreach ($requiredPermissions as $permission) {
            if (!array_key_exists($permission, $permissions)) {
                $missing[] = 'permission:' . $permission;
            }
        }

        foreach ($requiredActions as $action => $permission) {
            if (!isset($actions[$action])) {
                $missing[] = 'action:' . $action;
                continue;
            }

            if (!isset($actions[$action]['permission']) || $actions[$action]['permission'] !== $permission) {
                $missing[] = 'action_permission:' . $action;
            }
            if (empty($actions[$action]['step_up']) || empty($actions[$action]['confirm'])) {
                $missing[] = 'action_step_up:' . $action;
            }
        }

        return [
            'name' => 'admin_rbac_config',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B admin RBAC and privileged step-up configuration is present.'
                : 'Missing B2B admin RBAC configuration: ' . implode(', ', $missing),
        ];
    }

    private function webSurfacesCheck($production)
    {
        $missing = [];

        if (!Route::has('backend.b2b.dashboard')) {
            $missing[] = 'route:backend.b2b.dashboard';
        }

        if (!View::exists('backend.b2b.dashboard')) {
            $missing[] = 'view:backend.b2b.dashboard';
        }

        foreach (['backend.b2b.step_up.show', 'backend.b2b.step_up.store'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!View::exists('backend.b2b.step-up')) {
            $missing[] = 'view:backend.b2b.step-up';
        }

        if ($this->routeMiddlewareClass('b2b.web_step_up') !== 'VanguardLTE\Http\Middleware\RequireB2BWebStepUp') {
            $missing[] = 'middleware:b2b.web_step_up';
        }

        foreach (['api/b2b/v1/readiness', 'api/b2b/v1/metrics', 'api/b2b/v1/portal/overview'] as $uri) {
            if (!$this->routeExists('GET', $uri)) {
                $missing[] = 'route:' . $uri;
            }
        }

        return [
            'name' => 'web_surfaces',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B backend, operator portal, web step-up, readiness, and metrics web surfaces are registered.'
                : 'Missing B2B web surfaces: ' . implode(', ', $missing),
        ];
    }

    private function laravelSecurityMitigationsCheck($production)
    {
        $missing = [];

        if (!$this->hardenedValidatorRejectsCrlfEmail()) {
            $missing[] = 'validator:email_crlf_mitigation';
        }

        if (!$this->hardenedValidatorBlocksPhp8UploadExtension()) {
            $missing[] = 'validator:php8_upload_extension_mitigation';
        }

        if ($this->routeMiddlewareClass('signed') === 'Illuminate\Routing\Middleware\ValidateSignature') {
            $missing[] = 'middleware_alias:signed';
        }

        if ($this->usesLaravelSignedRouteMiddleware()) {
            $missing[] = 'route_middleware:signed';
        }

        if ($this->usesLaravelTemporarySignedUrls()) {
            $missing[] = 'temporary_signed_urls';
        }

        return [
            'name' => 'laravel_security_mitigations',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Laravel advisory mitigations are active for email validation, PHP upload extensions, signed-route exposure, and temporary signed URL exposure.'
                : 'Missing Laravel advisory mitigations: ' . implode(', ', $missing),
        ];
    }

    private function hardenedValidatorRejectsCrlfEmail()
    {
        try {
            $validator = app('validator')->make(
                ['email' => "ops@example.test\r\nBcc: attacker@example.test"],
                ['email' => 'email']
            );

            return $validator instanceof SecurityHardenedValidator && $validator->fails();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function hardenedValidatorBlocksPhp8UploadExtension()
    {
        $path = tempnam(sys_get_temp_dir(), 'bbb-upload-check-');
        if ($path === false) {
            return false;
        }

        try {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lUcQGQAAAABJRU5ErkJggg=='));
            $file = new \Illuminate\Http\UploadedFile($path, 'avatar.php8', 'image/png', null, true);
            $validator = app('validator')->make(['file' => $file], ['file' => 'file|mimes:png']);

            return $validator instanceof SecurityHardenedValidator && $validator->fails();
        } catch (\Exception $e) {
            return false;
        } finally {
            @unlink($path);
        }
    }

    private function usesLaravelSignedRouteMiddleware()
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if ($this->isLaravelSignedMiddlewareReference($middleware)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isLaravelSignedMiddlewareReference($middleware)
    {
        if (!is_string($middleware)) {
            return false;
        }

        $middleware = ltrim($middleware, '\\');

        return $middleware === 'signed'
            || strpos($middleware, 'signed:') === 0
            || $middleware === 'Illuminate\Routing\Middleware\ValidateSignature'
            || strpos($middleware, 'Illuminate\Routing\Middleware\ValidateSignature:') === 0;
    }

    protected function routeMiddlewareClass($alias)
    {
        $routerMiddleware = app('router')->getMiddleware();
        if (isset($routerMiddleware[$alias]) && is_string($routerMiddleware[$alias])) {
            return ltrim($routerMiddleware[$alias], '\\');
        }

        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $reflection = new \ReflectionClass($kernel);
            if (!$reflection->hasProperty('routeMiddleware')) {
                return null;
            }

            $property = $reflection->getProperty('routeMiddleware');
            $property->setAccessible(true);
            $middleware = $property->getValue($kernel);
            if (is_array($middleware) && isset($middleware[$alias]) && is_string($middleware[$alias])) {
                return ltrim($middleware[$alias], '\\');
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    protected function usesLaravelTemporarySignedUrls()
    {
        $patterns = [
            'temporarySignedRoute',
            'temporaryUrl',
            'temporaryUploadUrl',
            'buildTemporaryUrlsUsing',
        ];

        foreach ($this->firstPartySourceFiles() as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (strpos($contents, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function firstPartySourceFiles()
    {
        $roots = [
            'app/B2B',
            'app/Console',
            'app/Exceptions',
            'app/Http',
            'app/Jobs',
            'app/Lib',
            'app/Providers',
            'app/Repositories',
            'app/Services',
            'app/Support',
            'config',
            'resources/views',
            'routes',
        ];
        $self = realpath(__FILE__);

        foreach ($roots as $root) {
            $rootPath = base_path($root);
            if (!is_dir($rootPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $realPath = realpath($path);
                if ($realPath === $self) {
                    continue;
                }

                if (preg_match('/(\.php|\.blade\.php)$/', $path) !== 1) {
                    continue;
                }

                yield $path;
            }
        }
    }

    private function jsonFile($path)
    {
        if (!file_exists($path)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function fileContents($path)
    {
        return file_exists($path) ? file_get_contents($path) : '';
    }

    private function routeExists($method, $uri)
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    private function dependencyAuditCheck($production)
    {
        if (!file_exists(base_path('composer.lock'))) {
            return [
                'name' => 'dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'composer.lock is missing; dependency advisories cannot be verified.',
            ];
        }

        $result = $this->runDependencyAuditCommand();
        $payload = json_decode($result['output'], true);

        if (!is_array($payload)) {
            return [
                'name' => 'dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'Composer locked dependency audit could not be parsed: ' . trim($result['error'] ?: $result['output']),
            ];
        }

        $advisoryPackages = isset($payload['advisories']) && is_array($payload['advisories'])
            ? array_keys($payload['advisories'])
            : [];
        $advisoryCount = 0;
        foreach ($advisoryPackages as $package) {
            $advisoryCount += is_array($payload['advisories'][$package]) ? count($payload['advisories'][$package]) : 0;
        }

        $abandonedPackages = isset($payload['abandoned']) && is_array($payload['abandoned'])
            ? array_keys($payload['abandoned'])
            : [];
        $abandonedCount = count($abandonedPackages);

        if ($advisoryCount === 0 && $abandonedCount === 0) {
            return [
                'name' => 'dependency_audit',
                'status' => 'pass',
                'message' => 'Composer locked dependency audit has no advisories or abandoned packages.',
            ];
        }

        $parts = [];
        if ($advisoryCount > 0) {
            $parts[] = $advisoryCount . ' advisories across ' . count($advisoryPackages) . ' packages: ' . implode(', ', $advisoryPackages);
        }
        if ($abandonedCount > 0) {
            $parts[] = $abandonedCount . ' abandoned packages: ' . implode(', ', $abandonedPackages);
        }

        return [
            'name' => 'dependency_audit',
            'status' => $production ? 'fail' : 'warn',
            'message' => 'Composer locked dependency audit found ' . implode('; ', $parts) . '.',
        ];
    }

    protected function runDependencyAuditCommand()
    {
        $process = Process::fromShellCommandline('composer audit --locked --no-dev --format=json --abandoned=report', base_path());
        $process->setTimeout(120);
        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
