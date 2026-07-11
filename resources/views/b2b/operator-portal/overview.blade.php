<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B2B Portal - {{ $operator['name'] }}</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; color: #1f2933; background: #f4f6f8; }
        .topbar { background: #17202a; color: #fff; padding: 18px 28px; }
        .topbar h1 { margin: 0 0 4px; font-size: 22px; font-weight: 600; }
        .topbar p { margin: 0; color: #c7d0d9; font-size: 13px; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 14px; }
        .panel { background: #fff; border: 1px solid #d8dee6; border-radius: 4px; box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04); }
        .panel h2 { margin: 0; padding: 13px 16px; font-size: 15px; font-weight: 600; border-bottom: 1px solid #e6ebf0; }
        .metric { padding: 16px; }
        .metric .value { font-size: 26px; line-height: 1; font-weight: 700; color: #17202a; }
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
            .grid, .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .wrap { padding: 14px; }
            .grid, .grid.two { grid-template-columns: 1fr; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>{{ $operator['name'] }}</h1>
    <p>{{ $operator['id'] }} · {{ $operator['status'] }} · {{ $operator['default_currency'] }}</p>
</div>

<main class="wrap">
    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ number_format((int) $summary['players']) }}</div>
            <div class="label">Players</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format((int) $summary['active_sessions']) }}</div>
            <div class="label">Active Sessions</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format((int) $summary['wallet_transactions']) }}</div>
            <div class="label">Wallet Transactions</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format((int) $summary['open_reconciliation_items']) }}</div>
            <div class="label">Open Reconciliation</div>
        </div>
    </section>

    <section class="grid two">
        <div class="panel">
            <h2>Operator</h2>
            <table>
                <tbody>
                <tr><th>Callback</th><td>{{ $operator['wallet_callback_configured'] ? 'configured' : 'missing' }}</td></tr>
                <tr><th>Base URL</th><td>{{ $operator['base_url'] ?: 'n/a' }}</td></tr>
                <tr><th>Max RPS</th><td>{{ $operator['max_rps'] ?: 'n/a' }}</td></tr>
                <tr><th>Timeout</th><td>{{ $operator['wallet_timeout_ms'] ?: 'n/a' }} ms</td></tr>
                <tr><th>Circuit Open Until</th><td>{{ $operator['circuit_open_until'] ?: 'closed' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Credentials</h2>
            <table>
                <thead><tr><th>Key</th><th>Status</th><th>Scopes</th><th>Max RPS</th><th>Last Used</th></tr></thead>
                <tbody>
                @forelse($credentials['recent_keys'] as $key)
                    <tr>
                        <td>{{ $key['key_id'] }}</td>
                        <td><span class="status">{{ $key['status'] }}</span></td>
                        <td>{{ !empty($key['scopes']) ? implode(', ', $key['scopes']) : 'none' }}</td>
                        <td>{{ $key['max_rps'] ?: 'n/a' }}</td>
                        <td>{{ $key['last_used_at'] ?: 'never' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No credentials</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid two">
        <div class="panel">
            <h2>Game Assignments</h2>
            <table>
                <thead><tr><th>Game</th><th>Provider</th><th>Status</th><th>Real</th></tr></thead>
                <tbody>
                @forelse($game_assignments['recent_assignments'] as $assignment)
                    <tr>
                        <td>{{ $assignment['game_uid'] }}</td>
                        <td>{{ $assignment['provider'] }}</td>
                        <td><span class="status">{{ $assignment['status'] }}</span></td>
                        <td>{{ $assignment['real_enabled'] ? 'yes' : 'no' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No assignments</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Settlements</h2>
            <table>
                <thead><tr><th>Settlement</th><th>Status</th><th>Currency</th><th>Net</th></tr></thead>
                <tbody>
                @forelse($settlements['recent_settlements'] as $settlement)
                    <tr>
                        <td>{{ $settlement['settlement_uid'] }}</td>
                        <td><span class="status {{ $settlement['status'] === 'submitted' ? 'warn' : '' }}">{{ $settlement['status'] }}</span></td>
                        <td>{{ $settlement['currency'] }}</td>
                        <td>{{ $settlement['net_amount'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No settlements</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel section">
        <h2>Recent Transactions</h2>
        <table>
            <thead><tr><th>Transaction</th><th>Type</th><th>Status</th><th>Amount</th><th>Session</th><th>Detail Endpoint</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($recent_transactions as $transaction)
                <tr>
                    <td>{{ $transaction['transaction_uid'] }}</td>
                    <td>{{ $transaction['type'] }}</td>
                    <td><span class="status {{ in_array($transaction['status'], ['failed', 'timeout', 'unknown'], true) ? 'bad' : '' }}">{{ $transaction['status'] }}</span></td>
                    <td>{{ $transaction['amount'] }} {{ $transaction['currency'] }}</td>
                    <td>{{ $transaction['session_id'] }}</td>
                    <td>{{ isset($transaction['detail_endpoint']) ? $transaction['detail_endpoint'] : 'n/a' }}</td>
                    <td>{{ $transaction['created_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No transactions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Reconciliation</h2>
        <table>
            <thead><tr><th>Transaction</th><th>State</th><th>Status</th><th>Priority</th><th>Reason</th><th>Detail Endpoint</th><th>Thread Page</th><th>Detected</th></tr></thead>
            <tbody>
            @forelse($reconciliation['open_items'] as $item)
                <tr>
                    <td>{{ $item['transaction_uid'] }}</td>
                    <td>{{ $item['state'] }}</td>
                    <td>{{ $item['status'] }}</td>
                    <td><span class="status {{ $item['priority'] === 'high' ? 'bad' : '' }}">{{ $item['priority'] }}</span></td>
                    <td>{{ $item['reason'] }}</td>
                    <td>{{ isset($item['support_case_detail_endpoint']) ? $item['support_case_detail_endpoint'] : 'n/a' }}</td>
                    <td>{{ isset($item['support_case_thread_endpoint']) ? $item['support_case_thread_endpoint'] : 'n/a' }}</td>
                    <td>{{ $item['detected_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No open reconciliation items</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Support Tickets</h2>
        <table>
            <thead><tr><th>Ticket</th><th>Status</th><th>Priority</th><th>Subject</th><th>Messages</th><th>Latest Message</th><th>Detail Endpoint</th><th>Thread Page</th></tr></thead>
            <tbody>
            @forelse(isset($support['recent_tickets']) ? $support['recent_tickets'] : [] as $ticket)
                <tr>
                    <td>{{ $ticket['ticket_uid'] ?: 'n/a' }}</td>
                    <td><span class="status {{ in_array($ticket['status'], ['open', 'in_progress'], true) ? 'warn' : '' }}">{{ $ticket['status'] ?: 'unknown' }}</span></td>
                    <td><span class="status {{ in_array($ticket['priority'], ['high', 'urgent'], true) ? 'bad' : '' }}">{{ $ticket['priority'] ?: 'normal' }}</span></td>
                    <td>{{ $ticket['subject'] ?: 'n/a' }}</td>
                    <td>{{ number_format((int) (isset($ticket['message_count']) ? $ticket['message_count'] : 0)) }}</td>
                    <td>
                        @if(isset($ticket['latest_message']) && $ticket['latest_message'])
                            {{ $ticket['latest_message']['message'] ?: 'n/a' }}
                            <br><span class="muted">{{ $ticket['latest_message']['actor'] ?: 'unknown' }} / {{ $ticket['latest_message']['source'] ?: 'unknown' }}</span>
                        @else
                            <span class="muted">n/a</span>
                        @endif
                    </td>
                    <td>{{ isset($ticket['detail_endpoint']) ? $ticket['detail_endpoint'] : 'n/a' }}</td>
                    <td>{{ isset($ticket['thread_endpoint']) ? $ticket['thread_endpoint'] : 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No support tickets</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>API Logs</h2>
        <table>
            <thead><tr><th>Event</th><th>Actor</th><th>Subject</th><th>Reason</th><th>Created</th></tr></thead>
            <tbody>
            @forelse(isset($audit['recent_events']) ? $audit['recent_events'] : [] as $event)
                <tr>
                    <td>{{ $event['event_type'] ?: 'n/a' }}</td>
                    <td>{{ $event['actor'] ?: 'n/a' }}</td>
                    <td>{{ ($event['subject_type'] ?: 'n/a') }} / {{ $event['subject_id'] ?: 'n/a' }}</td>
                    <td>{{ $event['reason'] ?: ($event['metadata_summary'] ?: 'n/a') }}</td>
                    <td>{{ $event['created_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No API logs</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
