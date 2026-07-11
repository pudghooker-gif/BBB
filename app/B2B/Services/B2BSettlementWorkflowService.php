<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BSettlement;

class B2BSettlementWorkflowService
{
    private $audit;

    private $redactor;

    public function __construct(B2BOperatorAuditLogger $audit, B2BPayloadRedactor $redactor)
    {
        $this->audit = $audit;
        $this->redactor = $redactor;
    }

    public function exportForOperator(B2BOperator $operator, Carbon $from, Carbon $to, $currency, $format = 'csv', $actor = null, $reason = null)
    {
        $this->assertTablesReady();

        $currency = strtoupper(trim((string) $currency));
        if ($currency === '') {
            $currency = strtoupper((string) $operator->default_currency);
        }

        $format = $this->normalizeFormat($format);
        $settlementUid = $this->settlementUid($operator->id, $from, $to, $currency);
        $snapshot = $this->buildSnapshot($operator, $from, $to, $currency);

        $settlement = B2BSettlement::where('settlement_uid', $settlementUid)->first();
        if ($settlement && in_array($settlement->status, [B2BSettlement::STATUS_EXPORTED, B2BSettlement::STATUS_SUBMITTED, B2BSettlement::STATUS_APPROVED], true)) {
            $content = $this->renderExport($settlement, $settlement->export_format ?: $format);

            return $this->payload($settlement, $content);
        }

        if (!$settlement) {
            $settlement = new B2BSettlement();
            $settlement->settlement_uid = $settlementUid;
            $settlement->operator_id = $operator->id;
        }

        $settlement->period_start = $from;
        $settlement->period_end = $to;
        $settlement->currency = $currency;
        $settlement->bets_amount = $snapshot['totals']['bets'];
        $settlement->wins_amount = $snapshot['totals']['wins'];
        $settlement->refunds_amount = $snapshot['totals']['refunds'];
        $settlement->ggr_amount = $snapshot['totals']['ggr'];
        $settlement->aggregator_fee_amount = $snapshot['totals']['aggregator_fee'];
        $settlement->provider_fee_amount = $snapshot['totals']['provider_fee'];
        $settlement->net_amount = $snapshot['totals']['net'];
        $settlement->status = B2BSettlement::STATUS_EXPORTED;
        $settlement->export_format = $format;
        $settlement->exported_at = Carbon::now();
        $settlement->submitted_at = null;
        $settlement->submitted_by = null;
        $settlement->approved_at = null;
        $settlement->approved_by = null;
        $settlement->rejected_at = null;
        $settlement->rejected_by = null;
        $settlement->metadata = $snapshot;

        $content = $this->renderExport($settlement, $format, $snapshot);
        $settlement->export_hash = hash('sha256', $content);
        $metadata = $settlement->metadata ?: [];
        $metadata['export'] = [
            'format' => $format,
            'sha256' => $settlement->export_hash,
            'generated_at' => $settlement->exported_at->toIso8601String(),
        ];
        $settlement->metadata = $metadata;
        $settlement->save();

        $this->audit->record($operator, 'settlement.exported', 'settlement', $settlementUid, $actor ?: 'api:' . $operator->operator_uid, $this->safeText($reason ?: 'Settlement export generated.'), [
            'settlement_uid' => $settlementUid,
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
            'currency' => $currency,
            'format' => $format,
            'sha256' => $settlement->export_hash,
            'totals' => $snapshot['totals'],
        ]);

        return $this->payload($settlement, $content);
    }

    public function backofficeSettlement($settlementUid)
    {
        $this->assertSettlementTableReady();

        $settlement = $this->settlementByUid($settlementUid);
        $payload = $this->payload($settlement);

        $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : [];
        $redactedSnapshot = $this->redactor->redact($snapshot);
        $snapshotDisplay = json_encode($redactedSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return array_merge($payload['settlement'], [
            'snapshot_display' => $snapshotDisplay === false ? '' : $snapshotDisplay,
            'totals' => isset($redactedSnapshot['totals']) && is_array($redactedSnapshot['totals']) ? $redactedSnapshot['totals'] : [],
            'by_type' => isset($redactedSnapshot['by_type']) && is_array($redactedSnapshot['by_type']) ? $redactedSnapshot['by_type'] : [],
            'approval' => isset($redactedSnapshot['approval']) && is_array($redactedSnapshot['approval']) ? $redactedSnapshot['approval'] : [],
            'export' => [
                'format' => isset($payload['export']['format']) ? $payload['export']['format'] : null,
                'sha256' => isset($payload['export']['sha256']) ? $payload['export']['sha256'] : null,
            ],
        ]);
    }

    public function settlementForOperator($settlementUid, $operatorId)
    {
        $settlement = B2BSettlement::where('settlement_uid', $settlementUid)
            ->where('operator_id', $operatorId)
            ->first();

        if (!$settlement) {
            throw new InvalidArgumentException('Settlement was not found.');
        }

        return $settlement;
    }

    public function submit($settlementUid, $actor, $reason, array $privilege = [])
    {
        $settlement = $this->settlementByUid($settlementUid);
        $this->requireStatus($settlement, [B2BSettlement::STATUS_EXPORTED], 'Only exported settlements can be submitted for approval.');

        $reason = $this->safeText($reason);
        $settlement->status = B2BSettlement::STATUS_SUBMITTED;
        $settlement->submitted_at = Carbon::now();
        $settlement->submitted_by = $actor;
        $settlement->save();

        $this->auditSettlement($settlement, 'settlement.submitted', $actor, $reason, $privilege);

        return $settlement;
    }

    public function approve($settlementUid, $actor, $reason, array $privilege = [])
    {
        $settlement = $this->settlementByUid($settlementUid);
        $this->requireStatus($settlement, [B2BSettlement::STATUS_SUBMITTED], 'Only submitted settlements can be approved.');

        $reason = $this->safeText($reason);
        $settlement->status = B2BSettlement::STATUS_APPROVED;
        $settlement->approved_at = Carbon::now();
        $settlement->approved_by = $actor;
        $this->appendApprovalMetadata($settlement, 'approved', $actor, $reason);
        $settlement->save();

        $this->auditSettlement($settlement, 'settlement.approved', $actor, $reason, $privilege);

        return $settlement;
    }

    public function reject($settlementUid, $actor, $reason, array $privilege = [])
    {
        $settlement = $this->settlementByUid($settlementUid);
        $this->requireStatus($settlement, [B2BSettlement::STATUS_SUBMITTED], 'Only submitted settlements can be rejected.');

        $reason = $this->safeText($reason);
        $settlement->status = B2BSettlement::STATUS_REJECTED;
        $settlement->rejected_at = Carbon::now();
        $settlement->rejected_by = $actor;
        $this->appendApprovalMetadata($settlement, 'rejected', $actor, $reason);
        $settlement->save();

        $this->auditSettlement($settlement, 'settlement.rejected', $actor, $reason, $privilege);

        return $settlement;
    }

    public function payload(B2BSettlement $settlement, $content = null)
    {
        $metadata = $settlement->metadata ?: [];

        return [
            'settlement' => [
                'settlement_uid' => $settlement->settlement_uid,
                'operator_id' => $settlement->operator_id,
                'period_start' => $this->dateString($settlement->period_start),
                'period_end' => $this->dateString($settlement->period_end),
                'currency' => $settlement->currency,
                'bets_amount' => $this->decimalNormalize($settlement->bets_amount),
                'wins_amount' => $this->decimalNormalize($settlement->wins_amount),
                'refunds_amount' => $this->decimalNormalize($settlement->refunds_amount),
                'ggr_amount' => $this->decimalNormalize($settlement->ggr_amount),
                'aggregator_fee_amount' => $this->decimalNormalize($settlement->aggregator_fee_amount),
                'provider_fee_amount' => $this->decimalNormalize($settlement->provider_fee_amount),
                'net_amount' => $this->decimalNormalize($settlement->net_amount),
                'status' => $settlement->status,
                'export_format' => $settlement->export_format,
                'export_hash' => $settlement->export_hash,
                'exported_at' => $this->dateString($settlement->exported_at),
                'submitted_at' => $this->dateString($settlement->submitted_at),
                'submitted_by' => $settlement->submitted_by,
                'approved_at' => $this->dateString($settlement->approved_at),
                'approved_by' => $settlement->approved_by,
                'rejected_at' => $this->dateString($settlement->rejected_at),
                'rejected_by' => $settlement->rejected_by,
            ],
            'snapshot' => $metadata,
            'export' => [
                'format' => $settlement->export_format,
                'sha256' => $settlement->export_hash,
                'content' => $content,
            ],
        ];
    }

    private function assertTablesReady()
    {
        if (!Schema::hasTable('b2b_settlements') || !Schema::hasTable('b2b_wallet_transactions')) {
            throw new RuntimeException('Settlement or wallet transaction table is missing.');
        }

        foreach (['settlement_uid', 'export_hash', 'exported_at', 'submitted_at', 'approved_at', 'rejected_at'] as $column) {
            if (!Schema::hasColumn('b2b_settlements', $column)) {
                throw new RuntimeException('Settlement lifecycle columns are missing. Run migrations.');
            }
        }
    }

    private function assertSettlementTableReady()
    {
        if (!Schema::hasTable('b2b_settlements')) {
            throw new RuntimeException('Settlement table is missing. Run migrations.');
        }
    }

    private function buildSnapshot(B2BOperator $operator, Carbon $from, Carbon $to, $currency)
    {
        $rows = DB::table('b2b_wallet_transactions')
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->where('operator_id', $operator->id)
            ->where('status', 'success')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        $byType = [];
        foreach ($rows as $row) {
            $byType[(string) $row->type] = [
                'count' => (int) $row->count,
                'amount' => $this->decimalNormalize($row->amount),
            ];
        }

        foreach (['bet', 'win', 'refund', 'rollback'] as $type) {
            if (!isset($byType[$type])) {
                $byType[$type] = [
                    'count' => 0,
                    'amount' => '0.00000000',
                ];
            }
        }

        $bets = $byType['bet']['amount'];
        $wins = $byType['win']['amount'];
        $refunds = $byType['refund']['amount'];
        $ggr = $this->decimalSub($this->decimalSub($bets, $wins), $refunds);
        $aggregatorFee = $this->decimalMultiplyBps($ggr, (int) config('b2b.settlement_aggregator_fee_bps', 0));
        $providerFee = $this->decimalMultiplyBps($ggr, (int) config('b2b.settlement_provider_fee_bps', 0));
        $net = $this->decimalSub($this->decimalSub($ggr, $aggregatorFee), $providerFee);
        $transactionCount = 0;
        foreach ($byType as $row) {
            $transactionCount += (int) $row['count'];
        }

        return [
            'snapshot' => [
                'operator_uid' => $operator->operator_uid,
                'period_start' => $from->toIso8601String(),
                'period_end' => $to->toIso8601String(),
                'currency' => $currency,
                'created_at' => Carbon::now()->toIso8601String(),
            ],
            'totals' => [
                'transactions' => $transactionCount,
                'bets' => $bets,
                'wins' => $wins,
                'refunds' => $refunds,
                'rollbacks' => $byType['rollback']['amount'],
                'ggr' => $ggr,
                'aggregator_fee' => $aggregatorFee,
                'provider_fee' => $providerFee,
                'net' => $net,
            ],
            'by_type' => $byType,
        ];
    }

    private function settlementByUid($settlementUid)
    {
        $settlement = B2BSettlement::where('settlement_uid', $settlementUid)->first();
        if (!$settlement) {
            throw new InvalidArgumentException('Settlement was not found.');
        }

        return $settlement;
    }

    private function requireStatus(B2BSettlement $settlement, array $allowed, $message)
    {
        if (!in_array($settlement->status, $allowed, true)) {
            throw new RuntimeException($message);
        }
    }

    private function auditSettlement(B2BSettlement $settlement, $eventType, $actor, $reason, array $privilege)
    {
        $this->audit->record($settlement->operator_id, $eventType, 'settlement', $settlement->settlement_uid, $actor, $reason, [
            'settlement_uid' => $settlement->settlement_uid,
            'status' => $settlement->status,
            'currency' => $settlement->currency,
            'net_amount' => $this->decimalNormalize($settlement->net_amount),
            'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
            'step_up' => !empty($privilege['step_up']),
            'source' => isset($privilege['source']) ? $privilege['source'] : null,
            'ip_address' => isset($privilege['ip_address']) ? $privilege['ip_address'] : null,
            'user_agent' => isset($privilege['user_agent']) ? $privilege['user_agent'] : null,
        ]);
    }

    private function appendApprovalMetadata(B2BSettlement $settlement, $decision, $actor, $reason)
    {
        $metadata = $settlement->metadata ?: [];
        $metadata['approval'] = [
            'decision' => $decision,
            'actor' => $actor,
            'reason' => $reason,
            'decided_at' => Carbon::now()->toIso8601String(),
        ];
        $settlement->metadata = $metadata;
    }

    private function renderExport(B2BSettlement $settlement, $format, array $snapshot = null)
    {
        $snapshot = $snapshot ?: ($settlement->metadata ?: []);

        if ($format === 'json') {
            return json_encode([
                'settlement_uid' => $settlement->settlement_uid,
                'operator_id' => $settlement->operator_id,
                'period_start' => $this->dateString($settlement->period_start),
                'period_end' => $this->dateString($settlement->period_end),
                'currency' => $settlement->currency,
                'status' => $settlement->status ?: B2BSettlement::STATUS_EXPORTED,
                'totals' => isset($snapshot['totals']) ? $snapshot['totals'] : [],
                'by_type' => isset($snapshot['by_type']) ? $snapshot['by_type'] : [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $lines = [];
        $lines[] = 'field,value';
        foreach ([
            'settlement_uid' => $settlement->settlement_uid,
            'operator_id' => $settlement->operator_id,
            'period_start' => $this->dateString($settlement->period_start),
            'period_end' => $this->dateString($settlement->period_end),
            'currency' => $settlement->currency,
            'status' => $settlement->status ?: B2BSettlement::STATUS_EXPORTED,
        ] as $field => $value) {
            $lines[] = $this->csv([$field, $value]);
        }

        $lines[] = '';
        $lines[] = 'metric,amount';
        foreach (isset($snapshot['totals']) ? $snapshot['totals'] : [] as $metric => $amount) {
            $lines[] = $this->csv([$metric, $amount]);
        }

        $lines[] = '';
        $lines[] = 'transaction_type,count,amount';
        foreach (isset($snapshot['by_type']) ? $snapshot['by_type'] : [] as $type => $row) {
            $lines[] = $this->csv([$type, isset($row['count']) ? $row['count'] : 0, isset($row['amount']) ? $row['amount'] : '0.00000000']);
        }

        return implode("\n", $lines) . "\n";
    }

    private function csv(array $values)
    {
        $escaped = [];
        foreach ($values as $value) {
            $value = (string) $value;
            if (preg_match('/[",\r\n]/', $value)) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $escaped[] = $value;
        }

        return implode(',', $escaped);
    }

    private function normalizeFormat($format)
    {
        $format = strtolower(trim((string) $format));
        if ($format === '') {
            return 'csv';
        }
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new InvalidArgumentException('Unsupported settlement export format.');
        }

        return $format;
    }

    private function settlementUid($operatorId, Carbon $from, Carbon $to, $currency)
    {
        return 'stl_' . substr(hash('sha256', implode('|', [
            $operatorId,
            $from->copy()->utc()->format('Y-m-d H:i:s'),
            $to->copy()->utc()->format('Y-m-d H:i:s'),
            $currency,
        ])), 0, 24);
    }

    private function dateString($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    private function safeText($value, $limit = 1000)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = $this->redactor->storageValue($value);

        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }

    private function decimalAdd($left, $right, $scale = 8)
    {
        if (function_exists('bcadd')) {
            return bcadd((string) $left, (string) $right, $scale);
        }

        return $this->decimalFromMinorUnits(
            $this->signedIntegerAdd(
                $this->decimalToMinorUnits($left, $scale),
                $this->decimalToMinorUnits($right, $scale)
            ),
            $scale
        );
    }

    private function decimalSub($left, $right, $scale = 8)
    {
        if (function_exists('bcsub')) {
            return bcsub((string) $left, (string) $right, $scale);
        }

        return $this->decimalFromMinorUnits(
            $this->signedIntegerAdd(
                $this->decimalToMinorUnits($left, $scale),
                $this->negateIntegerString($this->decimalToMinorUnits($right, $scale))
            ),
            $scale
        );
    }

    private function decimalNormalize($value, $scale = 8)
    {
        return $this->decimalAdd('0', $value, $scale);
    }

    private function decimalMultiplyBps($amount, $bps, $scale = 8)
    {
        $bps = (int) $bps;
        if ($bps <= 0) {
            return $this->decimalNormalize('0', $scale);
        }

        if (function_exists('bcmul') && function_exists('bcdiv')) {
            return bcdiv(bcmul((string) $amount, (string) $bps, $scale + 4), '10000', $scale);
        }

        $minor = $this->decimalToMinorUnits($amount, $scale);
        $negative = strpos($minor, '-') === 0;
        $minor = ltrim($minor, '+-');
        $product = $this->multiplyAbsByInt($minor, $bps);
        $quotient = $this->divideAbsByInt($product, 10000);

        return $this->decimalFromMinorUnits($negative && $quotient !== '0' ? '-' . $quotient : $quotient, $scale);
    }

    private function decimalToMinorUnits($value, $scale)
    {
        $value = trim((string) $value);
        $negative = strpos($value, '-') === 0;
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $major = preg_replace('/[^0-9]/', '', $parts[0]);
        $minor = isset($parts[1]) ? preg_replace('/[^0-9]/', '', $parts[1]) : '';
        $minor = substr(str_pad($minor, $scale, '0'), 0, $scale);
        $units = ltrim(($major === '' ? '0' : $major) . $minor, '0');
        $units = $units === '' ? '0' : $units;

        return $negative && $units !== '0' ? '-' . $units : $units;
    }

    private function decimalFromMinorUnits($units, $scale)
    {
        $units = (string) $units;
        $negative = strpos($units, '-') === 0;
        $units = ltrim($units, '+-');
        $units = ltrim($units, '0');
        $units = $units === '' ? '0' : $units;
        $units = str_pad($units, $scale + 1, '0', STR_PAD_LEFT);
        $major = substr($units, 0, -$scale);
        $minor = substr($units, -$scale);

        return ($negative ? '-' : '') . $major . '.' . $minor;
    }

    private function signedIntegerAdd($left, $right)
    {
        $leftNegative = strpos($left, '-') === 0;
        $rightNegative = strpos($right, '-') === 0;
        $leftAbs = ltrim($left, '+-');
        $rightAbs = ltrim($right, '+-');

        if ($leftNegative === $rightNegative) {
            $sum = $this->addAbs($leftAbs, $rightAbs);
            return $leftNegative && $sum !== '0' ? '-' . $sum : $sum;
        }

        $compare = $this->compareAbs($leftAbs, $rightAbs);
        if ($compare === 0) {
            return '0';
        }

        if ($compare > 0) {
            $diff = $this->subAbs($leftAbs, $rightAbs);
            return $leftNegative && $diff !== '0' ? '-' . $diff : $diff;
        }

        $diff = $this->subAbs($rightAbs, $leftAbs);
        return $rightNegative && $diff !== '0' ? '-' . $diff : $diff;
    }

    private function negateIntegerString($value)
    {
        $value = (string) $value;
        if ($value === '0') {
            return '0';
        }

        return strpos($value, '-') === 0 ? substr($value, 1) : '-' . $value;
    }

    private function compareAbs($left, $right)
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        if (strlen($left) !== strlen($right)) {
            return strlen($left) > strlen($right) ? 1 : -1;
        }

        return strcmp($left, $right);
    }

    private function addAbs($left, $right)
    {
        $left = strrev(ltrim($left, '0') ?: '0');
        $right = strrev(ltrim($right, '0') ?: '0');
        $max = max(strlen($left), strlen($right));
        $carry = 0;
        $result = '';

        for ($i = 0; $i < $max; $i++) {
            $sum = (int) ($left[$i] ?? 0) + (int) ($right[$i] ?? 0) + $carry;
            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private function subAbs($left, $right)
    {
        $left = strrev(ltrim($left, '0') ?: '0');
        $right = strrev(ltrim($right, '0') ?: '0');
        $borrow = 0;
        $result = '';

        for ($i = 0; $i < strlen($left); $i++) {
            $digit = (int) $left[$i] - $borrow;
            $subtrahend = (int) ($right[$i] ?? 0);
            if ($digit < $subtrahend) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= (string) ($digit - $subtrahend);
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private function multiplyAbsByInt($value, $multiplier)
    {
        $value = strrev(ltrim((string) $value, '0') ?: '0');
        $carry = 0;
        $result = '';

        for ($i = 0; $i < strlen($value); $i++) {
            $product = ((int) $value[$i] * $multiplier) + $carry;
            $result .= (string) ($product % 10);
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result .= (string) ($carry % 10);
            $carry = intdiv($carry, 10);
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private function divideAbsByInt($value, $divisor)
    {
        $value = ltrim((string) $value, '0') ?: '0';
        $result = '';
        $remainder = 0;

        for ($i = 0; $i < strlen($value); $i++) {
            $number = ($remainder * 10) + (int) $value[$i];
            $result .= (string) intdiv($number, $divisor);
            $remainder = $number % $divisor;
        }

        return ltrim($result, '0') ?: '0';
    }
}
