<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;

class B2BApiKeyLifecycleCommandTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator('op_keys', 'key_initial', 'initial_secret_1234567890');
    }

    public function testRotateApiKeyRequiresActorAndReason()
    {
        $exitCode = Artisan::call('b2b:rotate-api-key', [
            'operator_uid' => 'op_keys',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, DB::table('b2b_operator_api_keys')->count());
        $this->assertSame(0, DB::table('b2b_operator_audit_events')->count());
    }

    public function testRotateApiKeyCreatesNewSecretRevokesExistingAndWritesAudit()
    {
        $exitCode = Artisan::call('b2b:rotate-api-key', [
            'operator_uid' => 'op_keys',
            '--key-id' => 'key_rotated',
            '--max-rps' => 5,
            '--actor' => 'security_user',
            '--reason' => 'Quarterly API key rotation.',
            '--revoke-existing' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('X-Api-Key:     key_rotated', $output);
        $this->assertStringContainsString('Secret:', $output);

        $this->assertSame(
            B2BOperatorApiKey::STATUS_DISABLED,
            DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status')
        );
        $this->assertSame(
            B2BOperatorApiKey::STATUS_ACTIVE,
            DB::table('b2b_operator_api_keys')->where('key_id', 'key_rotated')->value('status')
        );
        $this->assertSame(5, (int) DB::table('b2b_operator_api_keys')->where('key_id', 'key_rotated')->value('max_rps'));

        $revoked = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'api_key.revoked')
            ->where('subject_id', 'key_initial')
            ->first();

        $this->assertNotNull($revoked);
        $this->assertSame('security_user', $revoked->actor);
        $this->assertSame('Quarterly API key rotation.', $revoked->reason);
        $this->assertSame((string) $this->operator->id, (string) $revoked->operator_id);
        $this->assertSame('key_rotated', json_decode($revoked->metadata, true)['replacement_key_id']);

        $rotated = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'api_key.rotated')
            ->where('subject_id', 'key_rotated')
            ->first();

        $this->assertNotNull($rotated);
        $rotatedMetadata = json_decode($rotated->metadata, true);
        $this->assertTrue($rotatedMetadata['revoke_existing']);
        $this->assertSame(1, $rotatedMetadata['disabled_existing']);
        $this->assertSame(5, $rotatedMetadata['max_rps']);
    }

    public function testRevokeApiKeyRequiresActorAndReason()
    {
        $exitCode = Artisan::call('b2b:revoke-api-key', [
            'operator_uid' => 'op_keys',
            'key_id' => 'key_initial',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            B2BOperatorApiKey::STATUS_ACTIVE,
            DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status')
        );
        $this->assertSame(0, DB::table('b2b_operator_audit_events')->count());
    }

    public function testRevokeApiKeyDisablesCredentialAndWritesAudit()
    {
        $exitCode = Artisan::call('b2b:revoke-api-key', [
            'operator_uid' => 'op_keys',
            'key_id' => 'key_initial',
            '--actor' => 'ops_user',
            '--reason' => 'Partner requested credential revocation.',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            B2BOperatorApiKey::STATUS_DISABLED,
            DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status')
        );

        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'api_key.revoked')
            ->where('subject_id', 'key_initial')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('ops_user', $event->actor);
        $this->assertSame('Partner requested credential revocation.', $event->reason);
        $metadata = json_decode($event->metadata, true);
        $this->assertSame(B2BOperatorApiKey::STATUS_ACTIVE, $metadata['previous_status']);
        $this->assertSame(B2BOperatorApiKey::STATUS_DISABLED, $metadata['new_status']);
    }
}
