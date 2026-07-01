<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Models\B2BOperator;

class B2BOperatorConfigurationService
{
    private $audit;

    public function __construct(B2BOperatorAuditLogger $audit)
    {
        $this->audit = $audit;
    }

    public function update($operatorUid, array $input, $actor, $reason, array $context)
    {
        $this->assertTablesReady();
        $operator = $this->operatorByUid($operatorUid);
        $actor = $this->requiredText($actor, 'Operator update requires an actor.');
        $reason = $this->requiredText($reason, 'Operator update requires a reason.');

        $updates = [
            'name' => $this->requiredText(isset($input['name']) ? $input['name'] : null, 'Operator update requires a name.'),
            'shop_id' => $this->nullableInteger(isset($input['shop_id']) ? $input['shop_id'] : null),
            'base_url' => $this->nullableText(isset($input['base_url']) ? $input['base_url'] : null),
            'wallet_callback_url' => $this->nullableText(isset($input['wallet_callback_url']) ? $input['wallet_callback_url'] : null),
            'default_currency' => $this->currency(isset($input['default_currency']) ? $input['default_currency'] : null),
            'allowed_currencies' => $this->currencyList(isset($input['allowed_currencies']) ? $input['allowed_currencies'] : null),
            'allowed_countries' => $this->countryList(isset($input['allowed_countries']) ? $input['allowed_countries'] : null),
            'ip_whitelist' => $this->ipWhitelist(isset($input['ip_whitelist']) ? $input['ip_whitelist'] : null),
            'max_rps' => $this->integerRange(isset($input['max_rps']) ? $input['max_rps'] : null, 1, 100000, 'max_rps'),
            'wallet_timeout_ms' => $this->integerRange(isset($input['wallet_timeout_ms']) ? $input['wallet_timeout_ms'] : null, 100, 120000, 'wallet_timeout_ms'),
            'connect_timeout_ms' => $this->integerRange(isset($input['connect_timeout_ms']) ? $input['connect_timeout_ms'] : null, 100, 60000, 'connect_timeout_ms'),
            'circuit_breaker_threshold' => $this->integerRange(isset($input['circuit_breaker_threshold']) ? $input['circuit_breaker_threshold'] : null, 1, 1000, 'circuit_breaker_threshold'),
            'circuit_breaker_cooldown_seconds' => $this->integerRange(isset($input['circuit_breaker_cooldown_seconds']) ? $input['circuit_breaker_cooldown_seconds'] : null, 1, 86400, 'circuit_breaker_cooldown_seconds'),
        ];

        $before = $this->snapshot($operator, array_keys($updates));
        $operator->forceFill($updates)->save();
        $after = $this->snapshot($operator->fresh(), array_keys($updates));

        $this->audit->record($operator, 'operator.updated', 'operator', $operator->operator_uid, $actor, $reason, $this->metadata($context, [
            'operator_uid' => $operator->operator_uid,
            'changed_fields' => $this->changedFields($before, $after),
            'before' => $before,
            'after' => $after,
        ]));

        return $operator->fresh();
    }

    public function suspend($operatorUid, $actor, $reason, array $context)
    {
        return $this->statusChange($operatorUid, B2BOperator::STATUS_SUSPENDED, 'operator.suspended', $actor, $reason, $context);
    }

    public function resume($operatorUid, $actor, $reason, array $context)
    {
        return $this->statusChange($operatorUid, B2BOperator::STATUS_ACTIVE, 'operator.resumed', $actor, $reason, $context);
    }

    private function statusChange($operatorUid, $status, $eventType, $actor, $reason, array $context)
    {
        $this->assertTablesReady();
        $operator = $this->operatorByUid($operatorUid);
        $actor = $this->requiredText($actor, 'Operator status change requires an actor.');
        $reason = $this->requiredText($reason, 'Operator status change requires a reason.');
        $previousStatus = $operator->status;

        if ($operator->status !== $status) {
            $operator->forceFill(['status' => $status])->save();
        }

        $this->audit->record($operator, $eventType, 'operator', $operator->operator_uid, $actor, $reason, $this->metadata($context, [
            'operator_uid' => $operator->operator_uid,
            'previous_status' => $previousStatus,
            'new_status' => $status,
        ]));

        return $operator->fresh();
    }

    private function assertTablesReady()
    {
        foreach (['b2b_operators', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B operator/audit tables are missing. Run: php artisan migrate');
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

    private function nullableText($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function integerRange($value, $min, $max, $field)
    {
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException($field . ' must be between ' . $min . ' and ' . $max . '.');
        }

        return $value;
    }

    private function currency($value)
    {
        $currency = strtoupper(trim((string) $value));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Default currency must be an ISO-4217 style 3-letter code.');
        }

        return $currency;
    }

    private function currencyList($value)
    {
        $items = $this->splitList($value);
        $currencies = [];

        foreach ($items as $item) {
            $currencies[] = $this->currency($item);
        }

        return array_values(array_unique($currencies));
    }

    private function countryList($value)
    {
        $items = $this->splitList($value);
        $countries = [];

        foreach ($items as $item) {
            $country = strtoupper(trim((string) $item));
            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                throw new InvalidArgumentException('Allowed countries must be ISO-3166 alpha-2 codes.');
            }
            $countries[] = $country;
        }

        return array_values(array_unique($countries));
    }

    private function ipWhitelist($value)
    {
        $items = $this->splitList($value);
        $ips = [];

        foreach ($items as $item) {
            $item = trim((string) $item);
            if (!$this->validIpOrCidr($item)) {
                throw new InvalidArgumentException('IP whitelist contains an invalid IP or CIDR entry: ' . $item);
            }
            $ips[] = $item;
        }

        return array_values(array_unique($ips));
    }

    private function splitList($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/[\s,]+/', $value)));
    }

    private function validIpOrCidr($value)
    {
        if (strpos($value, '/') === false) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        $parts = explode('/', $value, 2);
        if (count($parts) !== 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false || !preg_match('/^\d+$/', $parts[1])) {
            return false;
        }

        $prefix = (int) $parts[1];
        $maxPrefix = strpos($parts[0], ':') === false ? 32 : 128;

        return $prefix >= 0 && $prefix <= $maxPrefix;
    }

    private function snapshot(B2BOperator $operator, array $fields)
    {
        $snapshot = [];
        foreach ($fields as $field) {
            $value = $operator->{$field};
            if (is_array($value)) {
                $value = array_values($value);
            }
            $snapshot[$field] = $value;
        }

        return $snapshot;
    }

    private function changedFields(array $before, array $after)
    {
        $changed = [];
        foreach ($after as $field => $value) {
            if (!array_key_exists($field, $before) || $before[$field] != $value) {
                $changed[] = $field;
            }
        }

        return $changed;
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
