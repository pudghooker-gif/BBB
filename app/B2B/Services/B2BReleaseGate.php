<?php

namespace VanguardLTE\B2B\Services;

class B2BReleaseGate
{
    public function run($production = false, $checkFiles = true)
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
        ];

        if ($checkFiles) {
            $checks[] = $this->secretFilesCheck($production);
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
}
