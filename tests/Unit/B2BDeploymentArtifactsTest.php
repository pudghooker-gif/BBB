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

    public function testReadmeDocumentsB2BOperationalEntryPoints()
    {
        $readme = file_get_contents(base_path('README.md'));

        foreach ([
            'BBB B2B Casino Aggregator',
            '/api/b2b/v1',
            '/backend/b2b',
            'docs/b2b/RELEASE_CHECKS.md',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
            'php artisan b2b:release-check --production',
            'php artisan b2b:evidence-template',
            'php artisan b2b:evidence-hash',
            'composer audit --locked --no-dev',
            'Current Launch Blockers',
            'Laravel 12 / PHP 8.3',
            'Composer audit is green',
        ] as $needle) {
            $this->assertStringContainsString($needle, $readme);
        }

        $this->assertStringNotContainsString('About Laravel', $readme);
        $this->assertStringNotContainsString('Laravel Sponsors', $readme);
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
        $this->assertStringContainsString('proxy_set_header Origin', $template);
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
        $lock = file_get_contents(base_path('PTWebSocket/pnpm-lock.yaml'));
        $socketConfig = json_decode(file_get_contents(base_path('deploy/websocket/socket_config2.production.example.json')), true);
        $healthcheck = file_get_contents(base_path('deploy/scripts/healthcheck.sh'));

        $this->assertSame('node Server.js', $package['scripts']['start']);
        $this->assertArrayHasKey('check:syntax', $package['scripts']);

        foreach (['ws', 'mysql2', 'ioredis', 'moment-timezone'] as $dependency) {
            $this->assertArrayHasKey($dependency, $package['dependencies']);
        }
        $this->assertSame('3.22.6', $package['dependencies']['mysql2']);
        $this->assertSame('0.6.2', $package['dependencies']['moment-timezone']);
        $this->assertSame('7.5.11', $package['dependencies']['ws']);
        $this->assertArrayNotHasKey('request', $package['dependencies']);
        $this->assertStringNotContainsString('request@', $lock);
        $this->assertStringNotContainsString('deprecated: request', $lock);
        $this->assertStringNotContainsString('mysql2@2.', $lock);
        $this->assertStringNotContainsString('ws@7.1.2', $lock);
        $this->assertStringNotContainsString('moment-timezone@0.5.32', $lock);

        $this->assertStringContainsString('serverConfig.listen_port', $server);
        $this->assertStringContainsString('serverConfig.listen_host', $server);
        $this->assertStringContainsString("require('./httpClient')", $server);
        $this->assertStringContainsString('verifyClient: verifyClient', $server);
        $this->assertStringContainsString('function allowedOrigin', $server);
        $this->assertStringContainsString('function tokenAllowed', $server);
        $this->assertStringContainsString('function healthResponse', $server);
        $this->assertStringContainsString('function validHandshakeMessage', $server);
        $this->assertStringContainsString('websocket.handshake_invalid', $server);
        $this->assertStringContainsString('ws.ping()', $server);
        $this->assertStringContainsString('structuredLog', $server);
        $this->assertStringNotContainsString('console.log(ck)', $server);
        $this->assertStringNotContainsString('console.log(body)', $server);
        $this->assertSame(12096, $socketConfig['port']);
        $this->assertSame(12097, $socketConfig['listen_port']);
        $this->assertSame('127.0.0.1', $socketConfig['listen_host']);
        $this->assertFalse($socketConfig['ssl']);
        $this->assertSame('/healthz', $socketConfig['health_path']);
        $this->assertSame('/readyz', $socketConfig['ready_path']);
        $this->assertSame(['https://b2b.example.com'], $socketConfig['allowed_origins']);
        $this->assertTrue($socketConfig['require_session_cookie']);
        $this->assertSame('BBB_WEBSOCKET_AUTH_TOKENS', $socketConfig['auth_tokens_env']);
        $this->assertArrayNotHasKey('auth_tokens', $socketConfig);
        $this->assertGreaterThan(0, $socketConfig['max_connections']);
        $this->assertGreaterThan(0, $socketConfig['heartbeat_interval_ms']);
        $this->assertGreaterThan(0, $socketConfig['idle_timeout_ms']);
        $this->assertTrue($socketConfig['log_json']);

        $this->assertStringContainsString('websocket_runtime', $releaseGate);
        $this->assertStringContainsString('websocket_dependency_audit', $releaseGate);
        $this->assertStringContainsString('pnpm install --frozen-lockfile --ignore-scripts', $workflow);
        $this->assertStringContainsString('pnpm audit --prod', $workflow);
        $this->assertStringContainsString('pnpm run check:syntax', $workflow);
        $this->assertStringContainsString('WEBSOCKET_TCP_HOST', $healthcheck);
        $this->assertStringContainsString('WEBSOCKET_HEALTH_URL', $healthcheck);
        $this->assertStringContainsString('/dev/tcp', $healthcheck);
    }

    public function testOperationalScriptsUseStrictModeAndNoInlineSecrets()
    {
        foreach ([
            'deploy/scripts/backup.sh',
            'deploy/scripts/restore.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/migration-rehearsal.sh',
            'deploy/scripts/b2b-smoke.sh',
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
        $this->assertStringContainsString('HEALTHCHECK_ARTIFACT_DIR', $healthcheck);
        $this->assertStringContainsString('b2b-healthcheck-', $healthcheck);
        $this->assertStringContainsString('b2b-release-check-', $healthcheck);

        $backup = file_get_contents(base_path('deploy/scripts/backup.sh'));
        $this->assertStringContainsString('BACKUP_ARTIFACT_DIR', $backup);
        $this->assertStringContainsString('b2b-backup-', $backup);
        $this->assertStringContainsString('.sha256', $backup);
        $this->assertStringContainsString('sha256_value', $backup);

        $restore = file_get_contents(base_path('deploy/scripts/restore.sh'));
        $this->assertStringContainsString('CONFIRM_RESTORE', $restore);
        $this->assertStringContainsString('gzip -dc', $restore);
        $this->assertStringContainsString('mysql', $restore);
        $this->assertStringContainsString('trap restore_up EXIT', $restore);
        $this->assertStringContainsString('b2b:release-check --production', $restore);
        $this->assertStringContainsString('RESTORE_ARTIFACT_DIR', $restore);
        $this->assertStringContainsString('b2b-restore-', $restore);
        $this->assertStringContainsString('b2b-restore-release-check-', $restore);
        $this->assertStringContainsString('sha256_value', $restore);

        $rollback = file_get_contents(base_path('deploy/scripts/rollback.sh'));
        $this->assertStringContainsString('ROLLBACK_ARTIFACT_DIR', $rollback);
        $this->assertStringContainsString('b2b-rollback-', $rollback);
        $this->assertStringContainsString('b2b-rollback-release-check-', $rollback);

        $migrationRehearsal = file_get_contents(base_path('deploy/scripts/migration-rehearsal.sh'));
        $this->assertStringContainsString('MIGRATION_REHEARSAL_ARTIFACT_DIR', $migrationRehearsal);
        $this->assertStringContainsString('ARTIFACT_DIR', $migrationRehearsal);
        $this->assertStringContainsString('CONFIRM_STAGING_MIGRATION=STAGING_MIGRATION_REHEARSAL', $migrationRehearsal);
        $this->assertStringContainsString('Refusing to run migration rehearsal against APP_ENV=production', $migrationRehearsal);
        $this->assertStringContainsString('trap cleanup_boot_cache EXIT', $migrationRehearsal);
        $this->assertStringContainsString('artisan migrate --pretend --force', $migrationRehearsal);
        $this->assertStringContainsString('artisan migrate --force', $migrationRehearsal);
        $this->assertStringContainsString('b2b-migration-rehearsal-', $migrationRehearsal);

        $smoke = file_get_contents(base_path('deploy/scripts/b2b-smoke.sh'));
        $this->assertStringContainsString('B2B_SMOKE_ARTIFACT_DIR', $smoke);
        $this->assertStringContainsString('b2b-smoke-', $smoke);
        $this->assertStringContainsString('/api/b2b/v1/health', $smoke);
        $this->assertStringContainsString('/api/b2b/v1/readiness', $smoke);
        $this->assertStringContainsString('/api/b2b/v1/metrics', $smoke);
        $this->assertStringContainsString('/api/b2b/v1/operator/me', $smoke);
        $this->assertStringContainsString('/api/b2b/v1/portal/overview', $smoke);
        $this->assertStringContainsString('B2B_SMOKE_OPERATOR_ID', $smoke);
        $this->assertStringContainsString('B2B_SMOKE_API_KEY', $smoke);
        $this->assertStringContainsString('B2B_SMOKE_API_SECRET', $smoke);
        $this->assertStringContainsString('hash_hmac("sha256"', $smoke);
    }

    public function testLoadTestArtifactsCoverPublicAndSignedB2BReads()
    {
        $script = file_get_contents(base_path('deploy/k6/b2b-smoke-load.js'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));

        foreach ([
            'constant-vus',
            'publicReadiness',
            'signedOperatorReads',
            '/api/b2b/v1/readiness',
            '/api/b2b/v1/metrics',
            '/api/b2b/v1/operator/me',
            '/api/b2b/v1/portal/overview',
            'B2B_OPERATOR_ID',
            'B2B_API_KEY',
            'B2B_API_SECRET',
            'K6_SUMMARY_PATH',
            'handleSummary',
            'signed_operator_checks_enabled',
            "crypto.hmac('sha256'",
            'http_req_failed',
            'http_req_duration',
        ] as $needle) {
            $this->assertStringContainsString($needle, $script);
        }

        $this->assertStringContainsString('deploy/k6/b2b-smoke-load.js', $releaseGate);
    }

    public function testReleaseEvidenceArtifactsAreDefinedAndReleaseChecked()
    {
        $template = json_decode(file_get_contents(base_path('deploy/evidence/release-evidence.example.json')), true);
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $console = file_get_contents(base_path('routes/b2b_console.php'));
        $runbook = file_get_contents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));
        $checks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        $this->assertArrayHasKey('evidence', $template);

        foreach ([
            'staging_migration_rehearsal',
            'production_release_gate',
            'payload_redaction_audit',
            'healthcheck',
            'smoke',
            'smoke_load',
            'websocket_public_proxy',
            'backup',
            'restore_rehearsal',
            'rollback_rehearsal',
            'prometheus_scrape',
            'alertmanager_notification',
            'log_shipping',
            'provider_credentials',
            'provider_certification',
            'legal_approval',
            'final_domains_tls',
        ] as $key) {
            $this->assertArrayHasKey($key, $template['evidence']);
            $entry = $template['evidence'][$key];
            if (isset($entry['artifacts'])) {
                $this->assertArrayHasKey('artifact_hashes', $entry, $key);
                foreach ($entry['artifacts'] as $artifact) {
                    $this->assertArrayHasKey($artifact, $entry['artifact_hashes'], $key . ':' . $artifact);
                }
            } else {
                $this->assertArrayHasKey('sha256', $entry, $key);
            }
        }

        $this->assertStringContainsString('deploy/evidence/release-evidence.example.json', $releaseGate);
        $this->assertStringContainsString('B2BReleaseEvidenceChecker', $releaseGate);
        $this->assertStringContainsString('b2b:evidence-template', $console);
        $this->assertStringContainsString('b2b:evidence-check', $console);
        $this->assertStringContainsString('b2b:evidence-hash', $console);
        $this->assertStringContainsString('release-evidence.json', $runbook);
        $this->assertStringContainsString('b2b:evidence-template', $runbook);
        $this->assertStringContainsString('b2b:evidence-check', $runbook);
        $this->assertStringContainsString('b2b:evidence-hash', $runbook);
        $this->assertStringContainsString('b2b:evidence-template', $checks);
        $this->assertStringContainsString('b2b:evidence-check', $checks);
        $this->assertStringContainsString('b2b:evidence-hash', $checks);
    }

    public function testPrometheusAlertArtifactsCoverB2BMetricsAndRoutes()
    {
        $alerts = file_get_contents(base_path('deploy/prometheus/b2b-alerts.yml'));
        $routes = file_get_contents(base_path('deploy/prometheus/alertmanager-routes.example.yml'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));

        foreach ([
            'bbb_b2b_metrics_collection_errors',
            'bbb_b2b_operator_circuit_open_total',
            'bbb_b2b_wallet_callbacks_total{outcome="failed"}',
            'bbb_b2b_wallet_transactions_status_total',
            'bbb_b2b_reconciliation_items_open_total',
            'bbb_b2b_queue_depth',
            'bbb_b2b_queue_oldest_job_age_seconds',
            'bbb_b2b_queue_failed_jobs_total',
            'bbb_b2b_scheduler_heartbeat_fresh',
            'BBBB2BQueueFailedJobs',
            'route: b2b-pager',
            'route: b2b-ops',
        ] as $needle) {
            $this->assertStringContainsString($needle, $alerts);
        }

        foreach ([
            'service="bbb-b2b"',
            'severity="critical"',
            'receiver: b2b-pager',
            'receiver: b2b-ops',
            'https://alertmanager.example.invalid/b2b-critical',
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        $this->assertStringContainsString('deploy/prometheus/b2b-alerts.yml', $releaseGate);
        $this->assertStringContainsString('deploy/prometheus/alertmanager-routes.example.yml', $releaseGate);
    }

    public function testRunbookDocumentsReleaseGateBackupsHealthAndRollback()
    {
        $runbook = file_get_contents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));

        foreach ([
            'php artisan b2b:release-check --production',
            'locked Composer dependency audit',
            'B2B Release Verification',
            'TRUSTED_PROXIES=',
            'B2B_NONCE_CACHE_STORE=redis',
            'B2B_RATE_LIMIT_CACHE_STORE=redis',
            'B2B_SCHEDULER_HEARTBEAT_CACHE_STORE=redis',
            'QUEUE_FAILED_DRIVER=database-uuids',
            'queue:failed',
            'queue:retry',
            'deploy/scripts/backup.sh',
            'deploy/scripts/restore.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/migration-rehearsal.sh',
            'deploy/scripts/b2b-smoke.sh',
            'deploy/k6/b2b-smoke-load.js',
            'MIGRATION_REHEARSAL_ARTIFACT_DIR',
            'HEALTHCHECK_ARTIFACT_DIR',
            'B2B_SMOKE_ARTIFACT_DIR',
            'K6_SUMMARY_PATH',
            'BACKUP_ARTIFACT_DIR',
            'RESTORE_ARTIFACT_DIR',
            'ROLLBACK_ARTIFACT_DIR',
            'deploy/prometheus/b2b-alerts.yml',
            'CONFIRM_RESTORE=RESTORE_BBB',
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
            'composer audit --locked --no-dev --format=plain --abandoned=fail',
            'php artisan b2b:release-check --production',
            'SESSION_DRIVER: database',
            'QUEUE_FAILED_DRIVER: database-uuids',
            'B2B_STRUCTURED_LOG_CHANNEL: b2b',
            'redis:7-alpine',
        ] as $needle) {
            $this->assertStringContainsString($needle, $workflow);
        }

        $this->assertStringNotContainsString('continue-on-error: true', $workflow);
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
            'deploy/scripts/restore.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/migration-rehearsal.sh',
            'deploy/scripts/b2b-smoke.sh',
            'deploy/k6/b2b-smoke-load.js',
            'deploy/evidence/release-evidence.example.json',
            'deploy/prometheus/b2b-alerts.yml',
            'deploy/prometheus/alertmanager-routes.example.yml',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
            'PTWebSocket/package.json',
            'PTWebSocket/pnpm-lock.yaml',
        ];
    }
}
