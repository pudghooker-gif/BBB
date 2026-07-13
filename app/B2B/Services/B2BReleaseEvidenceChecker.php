<?php

namespace VanguardLTE\B2B\Services;

class B2BReleaseEvidenceChecker
{
    public function requiredEvidence()
    {
        return [
            'staging_migration_rehearsal' => [
                'label' => 'Staging migration rehearsal on a restored production database copy',
                'statuses' => ['passed', 'verified'],
            ],
            'production_release_gate' => [
                'label' => 'php artisan b2b:release-check --production output',
                'statuses' => ['passed', 'verified'],
            ],
            'payload_redaction_audit' => [
                'label' => 'Final clean legacy B2B payload redaction audit artifact',
                'statuses' => ['passed', 'verified'],
            ],
            'healthcheck' => [
                'label' => 'deploy/scripts/healthcheck.sh output from the target topology',
                'statuses' => ['passed', 'verified'],
            ],
            'smoke' => [
                'label' => 'deploy/scripts/b2b-smoke.sh output with canary credentials',
                'statuses' => ['passed', 'verified'],
            ],
            'smoke_load' => [
                'label' => 'deploy/k6/b2b-smoke-load.js summary',
                'statuses' => ['passed', 'verified'],
            ],
            'websocket_public_proxy' => [
                'label' => 'Public WebSocket TLS/proxy and /healthz validation',
                'statuses' => ['passed', 'verified'],
            ],
            'backup' => [
                'label' => 'Backup job output plus off-host storage verification',
                'statuses' => ['passed', 'verified'],
            ],
            'restore_rehearsal' => [
                'label' => 'Restore drill output from staging',
                'statuses' => ['passed', 'verified'],
            ],
            'rollback_rehearsal' => [
                'label' => 'Rollback drill output from staging or canary',
                'statuses' => ['passed', 'verified'],
            ],
            'queue_runtime_drill' => [
                'label' => 'B2B queue worker, scheduler, and failed-job runtime drill',
                'statuses' => ['passed', 'verified'],
            ],
            'prometheus_scrape' => [
                'label' => 'Prometheus scrape/rule validation',
                'statuses' => ['passed', 'verified'],
            ],
            'alertmanager_notification' => [
                'label' => 'Alertmanager notification delivery test',
                'statuses' => ['passed', 'verified'],
            ],
            'log_shipping' => [
                'label' => 'B2B structured log shipping validation',
                'statuses' => ['passed', 'verified'],
            ],
            'correlation_validation' => [
                'label' => 'B2B wallet/provider correlation validation',
                'statuses' => ['passed', 'verified'],
            ],
            'provider_credentials' => [
                'label' => 'Real provider credential approval without inline secrets',
                'statuses' => ['approved', 'verified'],
                'requires_approval' => true,
            ],
            'provider_certification' => [
                'label' => 'Provider wallet contract/certification approval',
                'statuses' => ['approved', 'verified'],
                'requires_approval' => true,
            ],
            'legal_approval' => [
                'label' => 'Legal/compliance approval',
                'statuses' => ['approved', 'verified'],
                'requires_approval' => true,
            ],
            'final_domains_tls' => [
                'label' => 'Final production domains, TLS, trusted proxy, Redis, queue, worker, scheduler, and WebSocket host validation',
                'statuses' => ['passed', 'verified', 'approved'],
            ],
        ];
    }

    public function templateManifest($releaseId = null, $environment = 'production-canary', $commit = null, $generatedAt = null)
    {
        $generatedAt = $generatedAt ?: gmdate('Y-m-d\TH:i:s\Z');
        $stamp = $this->templateTimestamp($generatedAt);
        $evidence = [];

        foreach ($this->requiredEvidence() as $key => $requirement) {
            $artifacts = $this->templateArtifacts($key, $stamp);
            $statuses = isset($requirement['statuses']) && is_array($requirement['statuses'])
                ? $requirement['statuses']
                : ['passed'];
            $entry = [
                'label' => isset($requirement['label']) ? $requirement['label'] : $key,
                'status' => $statuses[0],
                'executed_at' => $generatedAt,
                'owner' => $this->templateOwner($key),
            ];

            if (count($artifacts) > 1) {
                $entry['artifacts'] = $artifacts;
                $entry['artifact_hashes'] = array_fill_keys($artifacts, str_repeat('0', 64));
            } else {
                $entry['artifact'] = $artifacts[0];
                $entry['sha256'] = str_repeat('0', 64);
            }

            if (!empty($requirement['requires_approval'])) {
                $entry['approved_by'] = $this->templateApprover($key);
            }

            $evidence[$key] = $entry;
        }

        return [
            'release_id' => $releaseId ?: gmdate('Y.m.d-His'),
            'environment' => $environment ?: 'production-canary',
            'commit' => $commit ?: $this->defaultCommitPlaceholder(),
            'generated_at' => $generatedAt,
            'redaction_note' => 'Store only redacted logs and approval references. Replace zero SHA-256 placeholders with real artifact hashes. Do not place API secrets, provider tokens, TLS keys, .env values, SQL dumps, or private certificates in this manifest.',
            'evidence' => $evidence,
        ];
    }

    public function check($directory, $production = false)
    {
        $checks = [];
        $base = realpath((string) $directory);

        if ($base === false || !is_dir($base)) {
            return [
                'ok' => false,
                'checks' => [[
                    'name' => 'evidence_directory',
                    'status' => 'fail',
                    'message' => 'Release evidence directory does not exist: ' . (string) $directory,
                ]],
            ];
        }

        $manifestPath = $base . DIRECTORY_SEPARATOR . 'release-evidence.json';
        if (!is_file($manifestPath)) {
            return [
                'ok' => false,
                'checks' => [[
                    'name' => 'release_evidence_manifest',
                    'status' => 'fail',
                    'message' => 'release-evidence.json is missing from: ' . $base,
                ]],
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return [
                'ok' => false,
                'checks' => [[
                    'name' => 'release_evidence_manifest',
                    'status' => 'fail',
                    'message' => 'release-evidence.json is not valid JSON.',
                ]],
            ];
        }

        $checks[] = $this->manifestMetadataCheck($manifest, (bool) $production);
        $checks[] = $this->manifestSecretsCheck($manifest);

        foreach ($this->requiredEvidence() as $key => $requirement) {
            $checks[] = $this->evidenceEntryCheck($base, $manifest, $key, $requirement, (bool) $production);
        }

        return [
            'ok' => $this->allPassed($checks),
            'checks' => $checks,
        ];
    }

    public function hashManifest($directory, $write = false)
    {
        $base = realpath((string) $directory);
        if ($base === false || !is_dir($base)) {
            return [
                'ok' => false,
                'written' => false,
                'hashes' => [],
                'checks' => [[
                    'name' => 'evidence_directory',
                    'status' => 'fail',
                    'message' => 'Release evidence directory does not exist: ' . (string) $directory,
                ]],
            ];
        }

        $manifestPath = $base . DIRECTORY_SEPARATOR . 'release-evidence.json';
        if (!is_file($manifestPath)) {
            return [
                'ok' => false,
                'written' => false,
                'hashes' => [],
                'checks' => [[
                    'name' => 'release_evidence_manifest',
                    'status' => 'fail',
                    'message' => 'release-evidence.json is missing from: ' . $base,
                ]],
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return [
                'ok' => false,
                'written' => false,
                'hashes' => [],
                'checks' => [[
                    'name' => 'release_evidence_manifest',
                    'status' => 'fail',
                    'message' => 'release-evidence.json is not valid JSON.',
                ]],
            ];
        }

        $checks = [];
        $hashes = [];
        $evidence = isset($manifest['evidence']) && is_array($manifest['evidence'])
            ? $manifest['evidence']
            : [];

        if (count($evidence) === 0) {
            $checks[] = [
                'name' => 'release_evidence_entries',
                'status' => 'fail',
                'message' => 'release-evidence.json has no evidence entries.',
            ];
        }

        foreach ($evidence as $key => $entry) {
            if (!is_array($entry)) {
                $checks[] = [
                    'name' => 'evidence_hash_' . $key,
                    'status' => 'fail',
                    'message' => 'Evidence entry is not an object: ' . $key,
                ];
                continue;
            }

            $artifacts = $this->entryArtifacts($entry);
            if (count($artifacts) === 0) {
                $checks[] = [
                    'name' => 'evidence_hash_' . $key,
                    'status' => 'fail',
                    'message' => 'Evidence entry has no artifacts: ' . $key,
                ];
                continue;
            }

            $entryHashes = [];
            foreach ($artifacts as $artifact) {
                $artifactIssue = $this->artifactIssue($base, $artifact);
                if ($artifactIssue !== null) {
                    $checks[] = [
                        'name' => 'evidence_hash_' . $key,
                        'status' => 'fail',
                        'message' => 'Cannot hash artifact: ' . $artifactIssue,
                    ];
                    continue;
                }

                $path = $this->artifactPath($base, $artifact);
                $entryHashes[$artifact] = hash_file('sha256', $path);
            }

            if (count($entryHashes) !== count($artifacts)) {
                continue;
            }

            $hashes[$key] = $entryHashes;
            if (isset($entry['artifacts']) && is_array($entry['artifacts'])) {
                $entry['artifact_hashes'] = $entryHashes;
                unset($entry['sha256']);
            } else {
                $entry['sha256'] = reset($entryHashes);
                unset($entry['artifact_hashes']);
            }

            $manifest['evidence'][$key] = $entry;
            $checks[] = [
                'name' => 'evidence_hash_' . $key,
                'status' => 'pass',
                'message' => 'Calculated SHA-256 for ' . count($entryHashes) . ' artifact(s).',
            ];
        }

        $ok = $this->allPassed($checks);
        $written = false;
        if ($ok && $write) {
            file_put_contents(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
            $written = true;
        }

        return [
            'ok' => $ok,
            'written' => $written,
            'hashes' => $hashes,
            'checks' => $checks,
        ];
    }

    private function manifestMetadataCheck(array $manifest, $production)
    {
        $missing = [];
        foreach (['release_id', 'environment', 'commit', 'generated_at'] as $key) {
            if (!isset($manifest[$key]) || trim((string) $manifest[$key]) === '') {
                $missing[] = $key;
            }
        }

        if (!isset($manifest['evidence']) || !is_array($manifest['evidence'])) {
            $missing[] = 'evidence';
        }

        if ($production) {
            $environment = isset($manifest['environment']) ? strtolower((string) $manifest['environment']) : '';
            if (!in_array($environment, ['staging', 'production-canary', 'production'], true)) {
                $missing[] = 'environment:staging|production-canary|production';
            }
        }

        return [
            'name' => 'manifest_metadata',
            'status' => count($missing) === 0 ? 'pass' : 'fail',
            'message' => count($missing) === 0
                ? 'Release evidence manifest metadata is complete.'
                : 'Release evidence manifest metadata is incomplete: ' . implode(', ', $missing),
        ];
    }

    private function manifestSecretsCheck(array $manifest)
    {
        $encoded = json_encode($manifest);

        foreach ($this->secretPatterns() as $pattern) {
            if (preg_match($pattern, (string) $encoded) === 1) {
                return [
                    'name' => 'manifest_secret_hygiene',
                    'status' => 'fail',
                    'message' => 'Release evidence manifest appears to contain inline secrets. Store only redacted references.',
                ];
            }
        }

        return [
            'name' => 'manifest_secret_hygiene',
            'status' => 'pass',
            'message' => 'Release evidence manifest does not contain known inline secret patterns.',
        ];
    }

    private function evidenceEntryCheck($base, array $manifest, $key, array $requirement, $production)
    {
        $evidence = isset($manifest['evidence']) && is_array($manifest['evidence'])
            ? $manifest['evidence']
            : [];

        if (!isset($evidence[$key]) || !is_array($evidence[$key])) {
            return $this->failedEvidenceCheck($key, 'Missing evidence entry: ' . $key);
        }

        $entry = $evidence[$key];
        $missing = [];
        $status = isset($entry['status']) ? strtolower((string) $entry['status']) : '';
        $acceptedStatuses = isset($requirement['statuses']) && is_array($requirement['statuses'])
            ? $requirement['statuses']
            : ['passed', 'verified'];

        if (!in_array($status, $acceptedStatuses, true)) {
            $missing[] = 'status:' . implode('|', $acceptedStatuses);
        }

        if (empty($entry['executed_at'])) {
            $missing[] = 'executed_at';
        }

        if (empty($entry['owner'])) {
            $missing[] = 'owner';
        }

        if ($production && !empty($requirement['requires_approval']) && empty($entry['approved_by'])) {
            $missing[] = 'approved_by';
        }

        $artifacts = $this->entryArtifacts($entry);
        if (count($artifacts) === 0) {
            $missing[] = 'artifact';
        }

        foreach ($artifacts as $artifact) {
            $artifactIssue = $this->artifactIssue($base, $artifact);
            if ($artifactIssue !== null) {
                $missing[] = $artifactIssue;
            }
        }

        foreach ($this->artifactHashIssues($base, $entry, $artifacts, $production) as $hashIssue) {
            $missing[] = $hashIssue;
        }

        return [
            'name' => 'evidence_' . $key,
            'status' => count($missing) === 0 ? 'pass' : 'fail',
            'message' => count($missing) === 0
                ? $requirement['label'] . ' evidence is present.'
                : $requirement['label'] . ' evidence is incomplete: ' . implode(', ', $missing),
        ];
    }

    private function entryArtifacts(array $entry)
    {
        if (isset($entry['artifacts']) && is_array($entry['artifacts'])) {
            return array_values(array_filter($entry['artifacts'], function ($artifact) {
                return is_string($artifact) && trim($artifact) !== '';
            }));
        }

        if (isset($entry['artifact']) && is_string($entry['artifact']) && trim($entry['artifact']) !== '') {
            return [$entry['artifact']];
        }

        return [];
    }

    private function artifactIssue($base, $artifact)
    {
        $path = $this->artifactPath($base, $artifact);
        if ($path === null) {
            return 'artifact_path:' . $artifact;
        }

        if (!is_file($path)) {
            return 'artifact_missing:' . $artifact;
        }

        if (filesize($path) <= 0) {
            return 'artifact_empty:' . $artifact;
        }

        if ($this->fileContainsSecretPattern($path)) {
            return 'artifact_secret_pattern:' . $artifact;
        }

        return null;
    }

    private function artifactHashIssues($base, array $entry, array $artifacts, $production)
    {
        $issues = [];

        foreach ($artifacts as $artifact) {
            $expected = $this->expectedArtifactHash($entry, $artifact, count($artifacts));

            if ($expected === null || $expected === '') {
                if ($production) {
                    $issues[] = 'sha256_missing:' . $artifact;
                }

                continue;
            }

            $expected = strtolower(trim((string) $expected));
            if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
                $issues[] = 'sha256_format:' . $artifact;
                continue;
            }

            $path = $this->artifactPath($base, $artifact);
            if ($path === null || !is_file($path)) {
                continue;
            }

            if (!hash_equals($expected, hash_file('sha256', $path))) {
                $issues[] = 'sha256_mismatch:' . $artifact;
            }
        }

        return $issues;
    }

    private function expectedArtifactHash(array $entry, $artifact, $artifactCount)
    {
        if (isset($entry['artifact_hashes']) && is_array($entry['artifact_hashes'])) {
            if (isset($entry['artifact_hashes'][$artifact])) {
                return $entry['artifact_hashes'][$artifact];
            }

            $normalized = str_replace('\\', '/', $artifact);
            if (isset($entry['artifact_hashes'][$normalized])) {
                return $entry['artifact_hashes'][$normalized];
            }
        }

        if ($artifactCount === 1 && isset($entry['sha256'])) {
            return $entry['sha256'];
        }

        return null;
    }

    private function templateArtifacts($key, $stamp)
    {
        $paths = [
            'staging_migration_rehearsal' => ['migration/b2b-migration-rehearsal-{stamp}.log'],
            'production_release_gate' => ['preflight/b2b-release-check-{stamp}.log'],
            'payload_redaction_audit' => ['payload-redaction-final.json'],
            'healthcheck' => [
                'preflight/b2b-healthcheck-{stamp}.log',
                'preflight/readiness-{stamp}.json',
                'preflight/metrics-{stamp}.txt',
            ],
            'smoke' => ['smoke/b2b-smoke-{stamp}.log'],
            'smoke_load' => ['load/k6-b2b-smoke-load-summary.json'],
            'websocket_public_proxy' => ['network/websocket-public-proxy-healthz.log'],
            'backup' => ['backup/backup-and-offhost-storage-verification.log'],
            'restore_rehearsal' => ['restore/staging-restore-drill.log'],
            'rollback_rehearsal' => ['rollback/staging-rollback-drill.log'],
            'queue_runtime_drill' => [
                'operations/b2b-queue-runtime-drill.log',
                'operations/b2b-queue-runtime-evidence.json',
            ],
            'prometheus_scrape' => ['observability/prometheus-scrape-and-rule-test.log'],
            'alertmanager_notification' => [
                'observability/alertmanager-delivery-test.log',
                'observability/alertmanager-receiver-delivery-confirmation.log',
            ],
            'log_shipping' => [
                'observability/b2b-log-shipping-validation.log',
                'observability/b2b-log-shipping-external-delivery.log',
            ],
            'correlation_validation' => ['observability/b2b-correlation-validation.json'],
            'provider_credentials' => ['provider/provider-credential-approval-redacted.txt'],
            'provider_certification' => ['provider/provider-wallet-contract-certification-redacted.txt'],
            'legal_approval' => ['compliance/legal-launch-approval-redacted.txt'],
            'final_domains_tls' => ['network/final-domains-tls-proxy-redis-queue-scheduler-validation.log'],
        ];

        $templates = isset($paths[$key]) ? $paths[$key] : ['artifacts/' . $key . '.log'];

        return array_map(function ($path) use ($stamp) {
            return str_replace('{stamp}', $stamp, $path);
        }, $templates);
    }

    private function templateOwner($key)
    {
        $owners = [
            'smoke' => 'qa',
            'smoke_load' => 'qa',
            'payload_redaction_audit' => 'security',
            'websocket_public_proxy' => 'platform',
            'backup' => 'database-operations',
            'restore_rehearsal' => 'database-operations',
            'queue_runtime_drill' => 'platform',
            'prometheus_scrape' => 'observability',
            'alertmanager_notification' => 'observability',
            'log_shipping' => 'observability',
            'correlation_validation' => 'observability',
            'provider_credentials' => 'integrations',
            'provider_certification' => 'integrations',
            'legal_approval' => 'compliance',
            'final_domains_tls' => 'platform',
        ];

        return isset($owners[$key]) ? $owners[$key] : 'release-engineering';
    }

    private function templateApprover($key)
    {
        if ($key === 'legal_approval') {
            return 'legal-owner';
        }

        return 'provider-owner';
    }

    private function templateTimestamp($generatedAt)
    {
        $timestamp = strtotime((string) $generatedAt);

        return $timestamp === false ? gmdate('Ymd\THis\Z') : gmdate('Ymd\THis\Z', $timestamp);
    }

    private function defaultCommitPlaceholder()
    {
        $sha = getenv('GITHUB_SHA') ?: getenv('CI_COMMIT_SHA');

        return $sha ?: 'replace-with-release-git-sha';
    }

    private function fileContainsSecretPattern($path)
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            while (!feof($handle)) {
                $chunk = fgets($handle, 1024 * 1024);
                if ($chunk === false) {
                    break;
                }

                foreach ($this->secretPatterns() as $pattern) {
                    if (preg_match($pattern, $chunk) === 1) {
                        return true;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private function secretPatterns()
    {
        return [
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
            '/APP_KEY\s*=/i',
            '/DB_PASSWORD\s*=/i',
            '/B2B_[A-Z_]*(SECRET|TOKEN|PASSWORD)\s*=/i',
            '/"secret"\s*:/i',
            '/"password"\s*:/i',
            '/"token"\s*:/i',
        ];
    }

    private function artifactPath($base, $artifact)
    {
        $artifact = str_replace('\\', '/', trim((string) $artifact));
        if ($artifact === '' || strpos($artifact, '..') !== false || preg_match('/^([a-z]:)?\//i', $artifact) === 1 || preg_match('/^[a-z]:/i', $artifact) === 1) {
            return null;
        }

        $candidate = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact));
        if ($candidate === false) {
            return null;
        }

        $normalizedBase = rtrim(strtolower(str_replace('\\', '/', $base)), '/');
        $normalizedCandidate = strtolower(str_replace('\\', '/', $candidate));
        if (strpos($normalizedCandidate, $normalizedBase . '/') !== 0) {
            return null;
        }

        return $candidate;
    }

    private function failedEvidenceCheck($key, $message)
    {
        return [
            'name' => 'evidence_' . $key,
            'status' => 'fail',
            'message' => $message,
        ];
    }

    private function allPassed(array $checks)
    {
        foreach ($checks as $check) {
            if (!isset($check['status']) || $check['status'] !== 'pass') {
                return false;
            }
        }

        return true;
    }
}
