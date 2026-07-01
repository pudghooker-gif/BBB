<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;

class B2BApiCredentialLifecycleService
{
    private $audit;

    public function __construct(B2BOperatorAuditLogger $audit)
    {
        $this->audit = $audit;
    }

    public function rotate($operatorUid, $actor, $reason, array $context, $keyId = null, $maxRps = null, $revokeExisting = false)
    {
        $this->assertTablesReady();
        $operator = $this->operatorByUid($operatorUid);
        $actor = $this->requiredText($actor, 'API key rotation requires an actor.');
        $reason = $this->requiredText($reason, 'API key rotation requires a reason.');

        $keyId = trim((string) $keyId);
        if ($keyId === '') {
            $keyId = 'key_' . Str::lower(Str::random(16));
        }

        if (B2BOperatorApiKey::where('key_id', $keyId)->exists()) {
            throw new InvalidArgumentException('B2B API key id already exists.');
        }

        $secret = Str::random(64);
        $apiKeyData = [
            'operator_id' => $operator->id,
            'key_id' => $keyId,
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => B2BOperatorApiKey::STATUS_ACTIVE,
        ];

        if (Schema::hasColumn('b2b_operator_api_keys', 'max_rps') && $maxRps !== null && $maxRps !== '') {
            $apiKeyData['max_rps'] = (int) $maxRps;
        }

        $apiKey = B2BOperatorApiKey::create($apiKeyData);

        $disabledExisting = 0;
        if ($revokeExisting) {
            $existingKeys = B2BOperatorApiKey::where('operator_id', $operator->id)
                ->where('id', '<>', $apiKey->id)
                ->where('status', B2BOperatorApiKey::STATUS_ACTIVE)
                ->get();

            foreach ($existingKeys as $existingKey) {
                $existingKey->forceFill(['status' => B2BOperatorApiKey::STATUS_DISABLED])->save();
                $disabledExisting++;

                $this->audit->record($operator, 'api_key.revoked', 'api_key', $existingKey->key_id, $actor, $reason, $this->metadata($context, [
                    'replacement_key_id' => $apiKey->key_id,
                    'previous_status' => B2BOperatorApiKey::STATUS_ACTIVE,
                    'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
                ]));
            }
        }

        $this->audit->record($operator, 'api_key.rotated', 'api_key', $apiKey->key_id, $actor, $reason, $this->metadata($context, [
            'key_id' => $apiKey->key_id,
            'max_rps' => isset($apiKeyData['max_rps']) ? $apiKeyData['max_rps'] : null,
            'revoke_existing' => (bool) $revokeExisting,
            'disabled_existing' => $disabledExisting,
        ]));

        return [
            'operator_uid' => $operator->operator_uid,
            'key_id' => $apiKey->key_id,
            'secret' => $secret,
            'disabled_existing' => $disabledExisting,
        ];
    }

    public function revoke($operatorUid, $keyId, $actor, $reason, array $context)
    {
        $this->assertTablesReady();
        $operator = $this->operatorByUid($operatorUid);
        $actor = $this->requiredText($actor, 'API key revocation requires an actor.');
        $reason = $this->requiredText($reason, 'API key revocation requires a reason.');
        $keyId = $this->requiredText($keyId, 'API key revocation requires a key id.');

        $apiKey = B2BOperatorApiKey::where('operator_id', $operator->id)
            ->where('key_id', $keyId)
            ->first();

        if (!$apiKey) {
            throw new InvalidArgumentException('B2B API key was not found for this operator.');
        }

        $previousStatus = $apiKey->status;
        if ($apiKey->status !== B2BOperatorApiKey::STATUS_DISABLED) {
            $apiKey->forceFill(['status' => B2BOperatorApiKey::STATUS_DISABLED])->save();
        }

        $this->audit->record($operator, $previousStatus === B2BOperatorApiKey::STATUS_DISABLED ? 'api_key.revoke_noop' : 'api_key.revoked', 'api_key', $apiKey->key_id, $actor, $reason, $this->metadata($context, [
            'previous_status' => $previousStatus,
            'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
        ]));

        return [
            'operator_uid' => $operator->operator_uid,
            'key_id' => $apiKey->key_id,
            'previous_status' => $previousStatus,
            'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
        ];
    }

    private function assertTablesReady()
    {
        foreach (['b2b_operators', 'b2b_operator_api_keys', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B credential/audit tables are missing. Run: php artisan migrate');
            }
        }
    }

    private function operatorByUid($operatorUid)
    {
        $operator = B2BOperator::where('operator_uid', trim((string) $operatorUid))->first();
        if (!$operator) {
            throw new InvalidArgumentException('B2B operator was not found.');
        }

        return $operator;
    }

    private function requiredText($value, $message)
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function metadata(array $context, array $extra)
    {
        return array_merge($extra, [
            'permission' => isset($context['permission']) ? $context['permission'] : null,
            'step_up' => !empty($context['step_up']),
            'source' => isset($context['source']) ? $context['source'] : null,
            'ip_address' => isset($context['ip_address']) ? $context['ip_address'] : null,
            'user_agent' => isset($context['user_agent']) ? $context['user_agent'] : null,
        ]);
    }
}
