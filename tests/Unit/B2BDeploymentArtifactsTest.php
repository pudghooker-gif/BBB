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
        $this->assertStringContainsString('fastcgi_pass bbb_b2b_php', $template);
        $this->assertStringContainsString('env|sql|bak|backup|old|key|crt|pem|log', $template);
        $this->assertStringContainsString('deny all', $template);
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
        $this->assertStringContainsString('"status":"ready"', $healthcheck);
    }

    public function testRunbookDocumentsReleaseGateBackupsHealthAndRollback()
    {
        $runbook = file_get_contents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));

        foreach ([
            'php artisan b2b:release-check --production',
            'B2B Release Verification',
            'B2B_NONCE_CACHE_STORE=redis',
            'B2B_RATE_LIMIT_CACHE_STORE=redis',
            'deploy/scripts/backup.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/rollback.sh',
            'External Launch Blockers',
            '/api/b2b/v1/readiness',
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
            'deploy/cron/bbb-maintenance.cron.example',
            'deploy/scripts/backup.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
        ];
    }
}
