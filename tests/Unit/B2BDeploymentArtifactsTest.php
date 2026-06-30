<?php

namespace Tests\Unit;

use Tests\TestCase;

class B2BDeploymentArtifactsTest extends TestCase
{
    public function testProductionDeploymentArtifactsArePresent()
    {
        foreach ($this->requiredArtifacts() as $path) {
            $this->assertFileExists(base_path($path), $path . ' is missing');
        }
    }

    public function testNginxTemplateProtectsKnownSecretAndLocalArtifactExtensions()
    {
        $template = file_get_contents(base_path('deploy/nginx/bbb-b2b.conf.example'));

        $this->assertStringContainsString('server_name b2b.example.com', $template);
        $this->assertStringContainsString('/api/b2b/v1/health', $template);
        $this->assertStringContainsString('/api/b2b/v1/readiness', $template);
        $this->assertStringContainsString('/api/b2b/v1/metrics', $template);
        $this->assertStringContainsString('fastcgi_pass bbb_b2b_php', $template);
        $this->assertStringContainsString('upstream bbb_b2b_websocket', $template);
        $this->assertStringContainsString('listen 12096 ssl', $template);
        $this->assertStringContainsString('proxy_set_header Upgrade', $template);
        $this->assertStringContainsString('proxy_buffering off', $template);
        $this->assertStringContainsString('env|sql|bak|backup|old|key|crt|pem|log', $template);
        $this->assertStringContainsString('deny all', $template);
    }

    public function testWebSocketRuntimeArtifactsAreInstallableAndReleaseChecked()
    {
        $package = json_decode(file_get_contents(base_path('PTWebSocket/package.json')), true);
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $workflow = file_get_contents(base_path('.github/workflows/b2b-release.yml'));
        $server = file_get_contents(base_path('PTWebSocket/Server.js'));
        $socketConfig = json_decode(file_get_contents(base_path('deploy/websocket/socket_config2.production.example.json')), true);
        $healthcheck = file_get_contents(base_path('deploy/scripts/healthcheck.sh'));

        $this->assertSame('node Server.js', $package['scripts']['start']);
        $this->assertArrayHasKey('check:syntax', $package['scripts']);

        foreach (['ws', 'request', 'mysql2', 'ioredis', 'moment-timezone'] as $dependency) {
            $this->assertArrayHasKey($dependency, $package['dependencies']);
        }

        $this->assertStringContainsString('serverConfig.listen_port', $server);
        $this->assertStringContainsString('serverConfig.listen_host', $server);
        $this->assertSame(12096, $socketConfig['port']);
        $this->assertSame(12097, $socketConfig['listen_port']);
        $this->assertSame('127.0.0.1', $socketConfig['listen_host']);
        $this->assertFalse($socketConfig['ssl']);

        $this->assertStringContainsString('websocket_runtime', $releaseGate);
        $this->assertStringContainsString('pnpm install --frozen-lockfile --ignore-scripts', $workflow);
        $this->assertStringContainsString('pnpm run check:syntax', $workflow);
        $this->assertStringContainsString('WEBSOCKET_TCP_HOST', $healthcheck);
        $this->assertStringContainsString('/dev/tcp', $healthcheck);
    }

    public function testOperationalScriptsUseStrictModeAndNoInlineSecrets()
    {
        foreach ([
            'deploy/scripts/backup.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
        ] as $path) {
            $script = file_get_contents(base_path($path));
            $this->assertStringContainsString('set -euo pipefail', $script, $path);
            $this->assertStringNotContainsString('password=', strtolower($script), $path);
            $this->assertStringNotContainsString('APP_KEY=', $script, $path);
        }

        $healthcheck = file_get_contents(base_path('deploy/scripts/healthcheck.sh'));
        $this->assertStringContainsString('/api/b2b/v1/readiness', $healthcheck);
        $this->assertStringContainsString('/api/b2b/v1/metrics', $healthcheck);
        $this->assertStringContainsString('"status":"ready"', $healthcheck);
        $this->assertStringContainsString('bbb_b2b_info', $healthcheck);
    }

    public function testRunbookDocumentsReleaseGateBackupsHealthAndRollback()
    {
        $runbook = file_get_contents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));

        foreach ([
            'php artisan b2b:release-check --production',
            'locked Composer dependency audit',
            'B2B Release Verification',
            'B2B_NONCE_CACHE_STORE=redis',
            'B2B_RATE_LIMIT_CACHE_STORE=redis',
            'deploy/scripts/backup.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/rollback.sh',
            'External Launch Blockers',
            '/api/b2b/v1/readiness',
            '/api/b2b/v1/metrics',
            '/backend/b2b',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runbook);
        }
    }

    public function testCiWorkflowCoversReleaseVerification()
    {
        $workflow = file_get_contents(base_path('.github/workflows/b2b-release.yml'));

        foreach ([
            'composer validate --strict',
            'composer install --prefer-dist --no-interaction --no-progress',
            'php artisan route:list --json',
            'php artisan route:cache',
            'php vendor/phpunit/phpunit/phpunit --testdox --colors=never',
            'composer audit --format=plain',
            'php artisan b2b:release-check --production',
            'continue-on-error: true',
            'redis:7-alpine',
        ] as $needle) {
            $this->assertStringContainsString($needle, $workflow);
        }
    }

    private function requiredArtifacts()
    {
        return [
            '.github/workflows/b2b-release.yml',
            'deploy/nginx/bbb-b2b.conf.example',
            'deploy/php-fpm/bbb-b2b.pool.conf.example',
            'deploy/supervisor/b2b-workers.conf.example',
            'deploy/systemd/bbb-scheduler.service',
            'deploy/systemd/bbb-scheduler.timer',
            'deploy/systemd/bbb-websocket.service',
            'deploy/websocket/socket_config2.production.example.json',
            'deploy/cron/bbb-maintenance.cron.example',
            'deploy/scripts/backup.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
            'PTWebSocket/package.json',
            'PTWebSocket/pnpm-lock.yaml',
        ];
    }
}
