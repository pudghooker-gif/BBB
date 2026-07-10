<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BReleaseEvidenceChecker;

class B2BReleaseEvidenceCheckerTest extends TestCase
{
    private $tempDirs = [];

    public function testCompleteProductionEvidencePackagePasses()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker);

        $result = $checker->check($dir, true);

        $this->assertTrue($result['ok'], json_encode($result['checks']));
        $this->assertCheckPassed($result, 'manifest_metadata');
        $this->assertCheckPassed($result, 'manifest_secret_hygiene');
        $this->assertCheckPassed($result, 'evidence_staging_migration_rehearsal');
        $this->assertCheckPassed($result, 'evidence_payload_redaction_audit');
        $this->assertCheckPassed($result, 'evidence_provider_certification');
        $this->assertCheckPassed($result, 'evidence_legal_approval');
    }

    public function testProductionEvidencePackageFailsWhenArtifactIsMissing()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker);
        @unlink($dir . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'smoke.log');

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_smoke');
    }

    public function testProductionEvidencePackageFailsWhenPayloadRedactionAuditEvidenceIsMissing()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            unset($manifest['evidence']['payload_redaction_audit']);
        });

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_payload_redaction_audit');
    }

    public function testProductionEvidencePackageFailsWhenArtifactHashIsMissing()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            unset($manifest['evidence']['smoke']['sha256']);
        });

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_smoke');
    }

    public function testProductionEvidencePackageFailsWhenArtifactHashDoesNotMatch()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            $manifest['evidence']['smoke']['sha256'] = str_repeat('0', 64);
        });

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_smoke');
    }

    public function testProductionEvidencePackagePassesWithMultipleArtifactHashes()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest, $dir) {
            $artifacts = [
                'artifacts/healthcheck.log',
                'artifacts/readiness.json',
                'artifacts/metrics.txt',
            ];

            foreach ($artifacts as $artifact) {
                file_put_contents($dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact), $artifact . PHP_EOL);
            }

            unset($manifest['evidence']['healthcheck']['artifact'], $manifest['evidence']['healthcheck']['sha256']);
            $manifest['evidence']['healthcheck']['artifacts'] = $artifacts;
            $manifest['evidence']['healthcheck']['artifact_hashes'] = [];
            foreach ($artifacts as $artifact) {
                $manifest['evidence']['healthcheck']['artifact_hashes'][$artifact] = hash_file('sha256', $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact));
            }
        });

        $result = $checker->check($dir, true);

        $this->assertTrue($result['ok'], json_encode($result['checks']));
        $this->assertCheckPassed($result, 'evidence_healthcheck');
    }

    public function testHashManifestDryRunDoesNotWriteHashes()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            unset($manifest['evidence']['smoke']['sha256']);
        });
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'release-evidence.json';
        $before = file_get_contents($manifestPath);

        $result = $checker->hashManifest($dir, false);

        $this->assertTrue($result['ok'], json_encode($result['checks']));
        $this->assertFalse($result['written']);
        $this->assertArrayHasKey('smoke', $result['hashes']);
        $this->assertSame($before, file_get_contents($manifestPath));
    }

    public function testHashManifestWritesSingleAndMultipleArtifactHashes()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest, $dir) {
            unset($manifest['evidence']['smoke']['sha256']);

            $artifacts = [
                'artifacts/healthcheck.log',
                'artifacts/readiness.json',
                'artifacts/metrics.txt',
            ];

            foreach ($artifacts as $artifact) {
                file_put_contents($dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact), $artifact . PHP_EOL);
            }

            unset($manifest['evidence']['healthcheck']['artifact'], $manifest['evidence']['healthcheck']['sha256']);
            $manifest['evidence']['healthcheck']['artifacts'] = $artifacts;
        });

        $result = $checker->hashManifest($dir, true);

        $this->assertTrue($result['ok'], json_encode($result['checks']));
        $this->assertTrue($result['written']);

        $manifest = json_decode(file_get_contents($dir . DIRECTORY_SEPARATOR . 'release-evidence.json'), true);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['evidence']['smoke']['sha256']);
        foreach ($manifest['evidence']['healthcheck']['artifacts'] as $artifact) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['evidence']['healthcheck']['artifact_hashes'][$artifact]);
        }

        $checkResult = $checker->check($dir, true);
        $this->assertTrue($checkResult['ok'], json_encode($checkResult['checks']));
    }

    public function testTemplateManifestUsesRequiredEvidenceAndHashPlaceholders()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $manifest = $checker->templateManifest(
            '2026.07.03-002',
            'staging',
            'abcdef1234567890',
            '2026-07-03T01:02:03Z'
        );

        $this->assertSame('2026.07.03-002', $manifest['release_id']);
        $this->assertSame('staging', $manifest['environment']);
        $this->assertSame('abcdef1234567890', $manifest['commit']);
        $this->assertSame('2026-07-03T01:02:03Z', $manifest['generated_at']);
        $this->assertSame(array_keys($checker->requiredEvidence()), array_keys($manifest['evidence']));
        $this->assertSame(
            'migration/b2b-migration-rehearsal-20260703T010203Z.log',
            $manifest['evidence']['staging_migration_rehearsal']['artifact']
        );
        $this->assertSame(
            'payload-redaction-final.json',
            $manifest['evidence']['payload_redaction_audit']['artifact']
        );

        foreach ($checker->requiredEvidence() as $key => $requirement) {
            $entry = $manifest['evidence'][$key];
            $this->assertSame($requirement['label'], $entry['label']);
            $this->assertNotEmpty($entry['owner']);
            $this->assertNotEmpty($entry['executed_at']);

            if (!empty($entry['artifacts'])) {
                foreach ($entry['artifacts'] as $artifact) {
                    $this->assertSame(str_repeat('0', 64), $entry['artifact_hashes'][$artifact]);
                }
            } else {
                $this->assertSame(str_repeat('0', 64), $entry['sha256']);
            }

            if (!empty($requirement['requires_approval'])) {
                $this->assertNotEmpty($entry['approved_by']);
            }
        }
    }

    public function testEvidenceTemplateCommandWritesManifestAndProtectsExistingFile()
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bbb-release-template-' . str_replace('.', '', uniqid('', true));
        $this->tempDirs[] = $dir;

        $created = Artisan::call('b2b:evidence-template', [
            'path' => $dir,
            '--release-id' => '2026.07.03-command',
            '--environment' => 'staging',
            '--commit' => 'commandsha',
            '--generated-at' => '2026-07-03T02:03:04Z',
        ]);

        $this->assertSame(0, $created);
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'release-evidence.json';
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('2026.07.03-command', $manifest['release_id']);
        $this->assertArrayHasKey('payload_redaction_audit', $manifest['evidence']);
        $this->assertArrayHasKey('legal_approval', $manifest['evidence']);

        $protected = Artisan::call('b2b:evidence-template', [
            'path' => $dir,
            '--release-id' => 'should-not-overwrite',
        ]);

        $this->assertSame(1, $protected);
        $unchanged = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('2026.07.03-command', $unchanged['release_id']);

        $forced = Artisan::call('b2b:evidence-template', [
            'path' => $dir,
            '--release-id' => '2026.07.03-forced',
            '--force' => true,
        ]);

        $this->assertSame(0, $forced);
        $overwritten = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('2026.07.03-forced', $overwritten['release_id']);
    }

    public function testHashManifestRefusesToWriteWhenArtifactIsMissing()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker);
        @unlink($dir . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'smoke.log');

        $result = $checker->hashManifest($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['written']);
        $this->assertCheckFailed($result, 'evidence_hash_smoke');
    }

    public function testProductionEvidencePackageFailsWhenApprovalOwnerIsMissing()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            unset($manifest['evidence']['legal_approval']['approved_by']);
        });

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_legal_approval');
    }

    public function testEvidenceManifestRejectsInlineSecretPatterns()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker, function (&$manifest) {
            $manifest['secret'] = 'do-not-store-real-secrets-here';
        });

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'manifest_secret_hygiene');
    }

    public function testEvidenceArtifactsRejectInlineSecretPatterns()
    {
        $checker = new B2BReleaseEvidenceChecker();
        $dir = $this->createEvidencePackage($checker);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'healthcheck.log', 'APP_KEY=base64:do-not-store-this');

        $result = $checker->check($dir, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'evidence_healthcheck');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }

        parent::tearDown();
    }

    private function createEvidencePackage(B2BReleaseEvidenceChecker $checker, callable $mutate = null)
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bbb-release-evidence-' . str_replace('.', '', uniqid('', true));
        mkdir($dir, 0777, true);
        mkdir($dir . DIRECTORY_SEPARATOR . 'artifacts', 0777, true);
        $this->tempDirs[] = $dir;

        $manifest = [
            'release_id' => '2026.07.03-test',
            'environment' => 'production-canary',
            'commit' => 'abcdef1234567890',
            'generated_at' => '2026-07-03T00:00:00Z',
            'evidence' => [],
        ];

        foreach ($checker->requiredEvidence() as $key => $requirement) {
            $artifact = 'artifacts/' . $key . '.log';
            $artifactPath = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact);
            file_put_contents($artifactPath, $key . ' evidence' . PHP_EOL);

            $statuses = isset($requirement['statuses']) ? $requirement['statuses'] : ['passed'];
            $entry = [
                'status' => $statuses[0],
                'executed_at' => '2026-07-03T00:00:00Z',
                'owner' => 'release-owner',
                'artifact' => $artifact,
                'sha256' => hash_file('sha256', $artifactPath),
            ];

            if (!empty($requirement['requires_approval'])) {
                $entry['approved_by'] = 'approval-owner';
            }

            $manifest['evidence'][$key] = $entry;
        }

        if ($mutate) {
            $mutate($manifest, $dir);
        }

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'release-evidence.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $dir;
    }

    private function assertCheckPassed(array $result, $name)
    {
        foreach ($result['checks'] as $check) {
            if ($check['name'] === $name) {
                $this->assertSame('pass', $check['status']);
                return;
            }
        }

        $this->fail('Release evidence check was not found: ' . $name);
    }

    private function assertCheckFailed(array $result, $name)
    {
        foreach ($result['checks'] as $check) {
            if ($check['name'] === $name) {
                $this->assertSame('fail', $check['status']);
                return;
            }
        }

        $this->fail('Release evidence check was not found: ' . $name);
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }
}
