<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BPayloadRedactionAuditor
{
    private $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function run($write = false, $limit = 0, $batchSize = 500)
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $write ? 'write' : 'dry-run',
            'scanned_rows' => 0,
            'scanned_fields' => 0,
            'findings' => 0,
            'updated_fields' => 0,
            'missing_targets' => [],
            'tables' => [],
        ];

        foreach ($this->targets() as $target) {
            $table = $target['table'];
            $tableReport = [
                'scanned_rows' => 0,
                'scanned_fields' => 0,
                'findings' => 0,
                'updated_fields' => 0,
                'columns' => [],
            ];

            if (!Schema::hasTable($table)) {
                $report['missing_targets'][] = $table;
                $report['tables'][$table] = $tableReport;
                continue;
            }

            $columns = [];
            foreach ($target['columns'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $columns[] = $column;
                    $tableReport['columns'][$column] = [
                        'scanned_fields' => 0,
                        'findings' => 0,
                        'updated_fields' => 0,
                    ];
                } else {
                    $report['missing_targets'][] = $table . '.' . $column;
                }
            }

            if (count($columns) === 0 || !Schema::hasColumn($table, 'id')) {
                $report['tables'][$table] = $tableReport;
                continue;
            }

            $limited = (int) $limit > 0;
            $remaining = (int) $limit;
            $batch = max(1, (int) $batchSize);
            $query = DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id');
            if ($remaining > 0) {
                $query->limit($remaining);
            }

            $query->chunk($batch, function ($rows) use ($table, $columns, $write, $limited, &$report, &$tableReport, &$remaining) {
                foreach ($rows as $row) {
                    if ($limited && $remaining === 0) {
                        return false;
                    }

                    $tableReport['scanned_rows']++;
                    $report['scanned_rows']++;

                    $updates = [];
                    foreach ($columns as $column) {
                        $tableReport['scanned_fields']++;
                        $tableReport['columns'][$column]['scanned_fields']++;
                        $report['scanned_fields']++;

                        $value = isset($row->{$column}) ? $row->{$column} : null;
                        if (!$this->needsRedaction($value)) {
                            continue;
                        }

                        $tableReport['findings']++;
                        $tableReport['columns'][$column]['findings']++;
                        $report['findings']++;

                        if ($write) {
                            $updates[$column] = $this->redactedValue($value);
                        }
                    }

                    if ($write && count($updates) > 0) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                        foreach (array_keys($updates) as $column) {
                            $tableReport['updated_fields']++;
                            $tableReport['columns'][$column]['updated_fields']++;
                            $report['updated_fields']++;
                        }
                    }

                    if ($limited) {
                        $remaining--;
                    }
                }

                return !$limited || $remaining !== 0;
            });

            $report['tables'][$table] = $tableReport;
        }

        return $report;
    }

    public function targets()
    {
        return [
            [
                'table' => 'b2b_wallet_transactions',
                'columns' => ['raw_request', 'raw_response', 'operator_response_body', 'last_error'],
            ],
            [
                'table' => 'b2b_wallet_callback_logs',
                'columns' => ['endpoint', 'request_body', 'response_body', 'error_message'],
            ],
            [
                'table' => 'b2b_wallet_transaction_attempts',
                'columns' => ['url', 'request_body', 'response_body', 'error'],
            ],
            [
                'table' => 'b2b_provider_requests',
                'columns' => ['request_payload', 'response_payload'],
            ],
            [
                'table' => 'b2b_sandbox_wallet_entries',
                'columns' => ['raw_payload', 'response_payload'],
            ],
        ];
    }

    private function needsRedaction($value)
    {
        if ($value === null || $value === '') {
            return false;
        }

        $redacted = $this->redactedValue($value);

        if (is_string($value) && is_string($redacted)) {
            $originalDecoded = json_decode($value, true);
            $redactedDecoded = json_decode($redacted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($redactedDecoded) && is_array($originalDecoded)) {
                return $originalDecoded !== $redactedDecoded;
            }

            return $value !== $redacted;
        }

        return $value !== $redacted;
    }

    private function redactedValue($value)
    {
        return $this->redactor->storageValue($value);
    }
}
