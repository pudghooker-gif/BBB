<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;

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
            $this->adminRbacCheck($production),
            $this->webSurfacesCheck($production),
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

        foreach (['api/b2b/v1/readiness', 'api/b2b/v1/metrics'] as $uri) {
            if (!$this->routeExists('GET', $uri)) {
                $missing[] = 'route:' . $uri;
            }
        }

        return [
            'name' => 'web_surfaces',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B backend, web step-up, readiness, and metrics web surfaces are registered.'
                : 'Missing B2B web surfaces: ' . implode(', ', $missing),
        ];
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
        $process = Process::fromShellCommandline('composer audit --locked --format=json --abandoned=report', base_path());
        $process->setTimeout(120);
        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
