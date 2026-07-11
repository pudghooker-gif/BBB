<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B2B Portal - {{ $portal_section['title'] }} - {{ $operator['name'] }}</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; color: #1f2933; background: #f4f6f8; }
        .topbar { background: #17202a; color: #fff; padding: 18px 28px; }
        .topbar h1 { margin: 0 0 4px; font-size: 22px; font-weight: 600; }
        .topbar p { margin: 0; color: #c7d0d9; font-size: 13px; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .nav a { display: inline-block; padding: 8px 10px; border: 1px solid #cbd5df; border-radius: 4px; color: #24445f; background: #fff; text-decoration: none; font-size: 13px; }
        .nav a.active { background: #24445f; color: #fff; border-color: #24445f; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
        .panel { background: #fff; border: 1px solid #d8dee6; border-radius: 4px; box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04); }
        .panel h2 { margin: 0; padding: 13px 16px; font-size: 15px; font-weight: 600; border-bottom: 1px solid #e6ebf0; }
        .metric { padding: 16px; }
        .metric .value { font-size: 22px; line-height: 1.1; font-weight: 700; color: #17202a; word-break: break-word; }
        .metric .label { margin-top: 6px; font-size: 12px; color: #627386; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 9px 12px; border-bottom: 1px solid #edf1f5; text-align: left; vertical-align: top; }
        th { color: #52616f; font-size: 11px; text-transform: uppercase; letter-spacing: .02em; background: #fafbfc; }
        .status { display: inline-block; min-width: 54px; padding: 3px 7px; border-radius: 3px; background: #e8f1fb; color: #184f80; font-size: 12px; }
        .status.warn { background: #fff4db; color: #835b00; }
        .status.bad { background: #fde8e8; color: #9b1c1c; }
        .muted { color: #7b8794; }
        .section { margin-top: 14px; }
        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .wrap { padding: 14px; }
            .grid { grid-template-columns: 1fr; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>{{ $operator['name'] }} - {{ $portal_section['title'] }}</h1>
    <p>{{ $operator['id'] }} / {{ $operator['status'] }} / {{ $operator['default_currency'] }}</p>
</div>

<main class="wrap">
    <nav class="nav">
        @foreach($portal_sections as $key => $label)
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'transactions' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ $detail['transaction']['transaction_uid'] ?: 'n/a' }}</div>
            <div class="label">Transaction</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['transaction']['status'] ?: 'unknown' }}</div>
            <div class="label">Status</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['transaction']['amount'] ?: '0.00000000' }} {{ $detail['transaction']['currency'] ?: '' }}</div>
            <div class="label">Amount</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format((int) $detail['transaction']['attempts']) }}</div>
            <div class="label">Attempts</div>
        </div>
    </section>

    <section class="panel section">
        <h2>Transaction Summary</h2>
        <table>
            <tbody>
            <tr><th>Operator Transaction</th><td>{{ $detail['transaction']['transaction_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Type</th><td>{{ $detail['transaction']['type'] ?: 'n/a' }}</td></tr>
            <tr><th>Status</th><td><span class="status {{ in_array($detail['transaction']['status'], ['failed', 'timeout', 'unknown', 'manual_review', 'dead_letter', 'rollback_required'], true) ? 'bad' : ($detail['transaction']['status'] === 'pending' ? 'warn' : '') }}">{{ $detail['transaction']['status'] ?: 'unknown' }}</span></td></tr>
            <tr><th>Session</th><td>{{ $detail['transaction']['session_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Game</th><td>{{ $detail['transaction']['game_uid'] ?: 'n/a' }}</td></tr>
            <tr><th>Round</th><td>{{ $detail['transaction']['round_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Player</th><td>{{ $detail['transaction']['player_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Last Error</th><td>{{ $detail['transaction']['last_error_summary'] ?: 'n/a' }}</td></tr>
            <tr><th>Portal Detail Endpoint</th><td>{{ $detail['detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>JSON Report Detail</th><td>{{ $detail['report_detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Next Actions</th><td>{{ count($detail['next_actions']) ? implode(', ', $detail['next_actions']) : 'n/a' }}</td></tr>
            <tr><th>Processed</th><td>{{ $detail['transaction']['processed_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Created</th><td>{{ $detail['transaction']['created_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Updated</th><td>{{ $detail['transaction']['updated_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Status Transitions</h2>
        <table>
            <thead><tr><th>Created</th><th>From</th><th>To</th><th>Reason</th><th>Actor</th><th>Context</th></tr></thead>
            <tbody>
            @forelse($detail['transitions'] as $transition)
                <tr>
                    <td>{{ $transition['created_at'] ?: 'n/a' }}</td>
                    <td>{{ $transition['from_status'] ?: 'n/a' }}</td>
                    <td>{{ $transition['to_status'] ?: 'n/a' }}</td>
                    <td>{{ $transition['reason'] ?: 'n/a' }}</td>
                    <td>{{ $transition['actor'] ?: 'system' }}</td>
                    <td>{{ $transition['context_summary'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No transitions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Callback Attempts</h2>
        <table>
            <thead><tr><th>Attempt</th><th>Type</th><th>Result</th><th>HTTP</th><th>Duration</th><th>Endpoint</th><th>Error</th><th>Finished</th></tr></thead>
            <tbody>
            @forelse($detail['attempts'] as $attempt)
                <tr>
                    <td>{{ $attempt['attempt_no'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['type'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['result'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['http_status'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['duration_ms'] !== null ? $attempt['duration_ms'] . ' ms' : 'n/a' }}</td>
                    <td>{{ $attempt['endpoint'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['error_summary'] ?: 'n/a' }}</td>
                    <td>{{ $attempt['finished_at'] ?: ($attempt['created_at'] ?: 'n/a') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No callback attempts</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Callback Logs</h2>
        <table>
            <thead><tr><th>Created</th><th>Direction</th><th>Result</th><th>HTTP</th><th>Duration</th><th>Endpoint</th><th>Error</th></tr></thead>
            <tbody>
            @forelse($detail['callback_logs'] as $log)
                <tr>
                    <td>{{ $log['created_at'] ?: 'n/a' }}</td>
                    <td>{{ $log['direction'] ?: 'n/a' }}</td>
                    <td>{{ $log['result'] ?: 'unknown' }}</td>
                    <td>{{ $log['http_status'] ?: 'n/a' }}</td>
                    <td>{{ $log['duration_ms'] !== null ? $log['duration_ms'] . ' ms' : 'n/a' }}</td>
                    <td>{{ $log['endpoint'] ?: 'n/a' }}</td>
                    <td>{{ $log['error_summary'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No callback logs</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Reconciliation Items</h2>
        <table>
            <thead><tr><th>State</th><th>Status</th><th>Priority</th><th>Reason</th><th>Detected</th><th>Resolved</th></tr></thead>
            <tbody>
            @forelse($detail['reconciliation_items'] as $item)
                <tr>
                    <td>{{ $item['state'] ?: 'n/a' }}</td>
                    <td>{{ $item['status'] ?: 'n/a' }}</td>
                    <td>{{ $item['priority'] ?: 'normal' }}</td>
                    <td>{{ $item['reason'] ?: 'n/a' }}</td>
                    <td>{{ $item['detected_at'] ?: 'n/a' }}</td>
                    <td>{{ $item['resolved_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No reconciliation items</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Manual Actions</h2>
        <table>
            <thead><tr><th>Created</th><th>Action</th><th>From</th><th>To</th><th>Actor</th><th>Reason</th><th>Context</th></tr></thead>
            <tbody>
            @forelse($detail['manual_actions'] as $action)
                <tr>
                    <td>{{ $action['created_at'] ?: 'n/a' }}</td>
                    <td>{{ $action['action'] ?: 'n/a' }}</td>
                    <td>{{ $action['from_status'] ?: 'n/a' }}</td>
                    <td>{{ $action['to_status'] ?: 'n/a' }}</td>
                    <td>{{ $action['actor'] ?: 'n/a' }}</td>
                    <td>{{ $action['reason'] ?: 'n/a' }}</td>
                    <td>{{ $action['context_summary'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No manual actions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
