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
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
        .panel { background: #fff; border: 1px solid #d8dee6; border-radius: 4px; box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04); }
        .panel h2 { margin: 0; padding: 13px 16px; font-size: 15px; font-weight: 600; border-bottom: 1px solid #e6ebf0; }
        .metric { padding: 16px; }
        .metric .value { font-size: 24px; line-height: 1; font-weight: 700; color: #17202a; }
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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $portal_section['key'] === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($portal_section['key'] === 'credentials')
        <section class="grid">
            @foreach($credentials['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} keys</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Credentials</h2>
            <table>
                <thead><tr><th>Key</th><th>Status</th><th>Max RPS</th><th>Last Used</th><th>Expires</th><th>Created</th></tr></thead>
                <tbody>
                @forelse($credentials['recent_keys'] as $key)
                    <tr>
                        <td>{{ $key['key_id'] }}</td>
                        <td><span class="status">{{ $key['status'] }}</span></td>
                        <td>{{ $key['max_rps'] ?: 'n/a' }}</td>
                        <td>{{ $key['last_used_at'] ?: 'never' }}</td>
                        <td>{{ $key['expires_at'] ?: 'n/a' }}</td>
                        <td>{{ $key['created_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No credentials</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'games')
        <section class="grid">
            @foreach($game_assignments['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} assignments</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Game Assignments</h2>
            <table>
                <thead><tr><th>Game</th><th>Provider</th><th>Status</th><th>Demo</th><th>Real</th><th>Updated</th></tr></thead>
                <tbody>
                @forelse($game_assignments['recent_assignments'] as $assignment)
                    <tr>
                        <td>{{ $assignment['game_uid'] }}</td>
                        <td>{{ $assignment['provider'] }}</td>
                        <td><span class="status">{{ $assignment['status'] }}</span></td>
                        <td>{{ $assignment['demo_enabled'] ? 'yes' : 'no' }}</td>
                        <td>{{ $assignment['real_enabled'] ? 'yes' : 'no' }}</td>
                        <td>{{ $assignment['updated_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No assignments</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'sessions')
        <section class="grid">
            @foreach($sessions['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} sessions</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Sessions</h2>
            <table>
                <thead><tr><th>Session</th><th>Player</th><th>Game</th><th>Status</th><th>Currency</th><th>Created</th><th>Expires</th></tr></thead>
                <tbody>
                @forelse($recent_sessions as $session)
                    <tr>
                        <td>{{ $session['session_uid'] }}</td>
                        <td>{{ $session['external_player_id'] ?: 'n/a' }}</td>
                        <td>{{ $session['game_uid'] }}</td>
                        <td><span class="status">{{ $session['status'] }}</span></td>
                        <td>{{ $session['currency'] }}</td>
                        <td>{{ $session['created_at'] ?: 'n/a' }}</td>
                        <td>{{ $session['expires_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No sessions</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'transactions')
        <section class="grid">
            @foreach($wallet['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} transactions</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Transactions</h2>
            <table>
                <thead><tr><th>Transaction</th><th>Type</th><th>Status</th><th>Amount</th><th>Session</th><th>Game</th><th>Created</th></tr></thead>
                <tbody>
                @forelse($recent_transactions as $transaction)
                    <tr>
                        <td>{{ $transaction['transaction_uid'] }}</td>
                        <td>{{ $transaction['type'] }}</td>
                        <td><span class="status {{ in_array($transaction['status'], ['failed', 'timeout', 'unknown'], true) ? 'bad' : '' }}">{{ $transaction['status'] }}</span></td>
                        <td>{{ $transaction['amount'] }} {{ $transaction['currency'] }}</td>
                        <td>{{ $transaction['session_id'] }}</td>
                        <td>{{ $transaction['game_uid'] }}</td>
                        <td>{{ $transaction['created_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No transactions</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'settlements')
        <section class="grid">
            @foreach($settlements['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} settlements</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Settlements</h2>
            <table>
                <thead><tr><th>Settlement</th><th>Status</th><th>Currency</th><th>GGR</th><th>Net</th><th>Period</th></tr></thead>
                <tbody>
                @forelse($settlements['recent_settlements'] as $settlement)
                    <tr>
                        <td>{{ $settlement['settlement_uid'] }}</td>
                        <td><span class="status {{ $settlement['status'] === 'submitted' ? 'warn' : '' }}">{{ $settlement['status'] }}</span></td>
                        <td>{{ $settlement['currency'] }}</td>
                        <td>{{ $settlement['ggr_amount'] }}</td>
                        <td>{{ $settlement['net_amount'] }}</td>
                        <td>{{ $settlement['period_start'] ?: 'n/a' }} - {{ $settlement['period_end'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No settlements</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'cases')
        <section class="grid">
            @foreach($reconciliation['by_state'] as $state => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $state }} cases</div>
                </div>
            @endforeach
        </section>

        <section class="panel section">
            <h2>Cases</h2>
            <table>
                <thead><tr><th>Transaction</th><th>State</th><th>Status</th><th>Priority</th><th>Reason</th><th>Detected</th></tr></thead>
                <tbody>
                @forelse($reconciliation['open_items'] as $item)
                    <tr>
                        <td>{{ $item['transaction_uid'] }}</td>
                        <td>{{ $item['state'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td><span class="status {{ $item['priority'] === 'high' ? 'bad' : '' }}">{{ $item['priority'] }}</span></td>
                        <td>{{ $item['reason'] }}</td>
                        <td>{{ $item['detected_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No open cases</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'callbacks')
        <section class="grid">
            @forelse($callbacks['by_result'] as $result => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $result }} callbacks</div>
                </div>
            @empty
                <div class="panel metric">
                    <div class="value">0</div>
                    <div class="label">Callbacks</div>
                </div>
            @endforelse
        </section>

        <section class="panel section">
            <h2>Callback Settings</h2>
            <table>
                <tbody>
                <tr><th>Wallet Callback</th><td>{{ $operator['wallet_callback_url'] ?: 'missing' }}</td></tr>
                <tr><th>Base URL</th><td>{{ $operator['base_url'] ?: 'n/a' }}</td></tr>
                <tr><th>Wallet Timeout</th><td>{{ $operator['wallet_timeout_ms'] ?: 'n/a' }} ms</td></tr>
                <tr><th>Connect Timeout</th><td>{{ $operator['connect_timeout_ms'] ?: 'n/a' }} ms</td></tr>
                <tr><th>Failure Count</th><td>{{ $operator['failure_count'] === null ? 'n/a' : number_format((int) $operator['failure_count']) }}</td></tr>
                <tr><th>Circuit Open Until</th><td>{{ $operator['circuit_open_until'] ?: 'closed' }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Callback Logs</h2>
            <table>
                <thead><tr><th>Transaction</th><th>Endpoint</th><th>Result</th><th>Status</th><th>Duration</th><th>Error</th><th>Created</th></tr></thead>
                <tbody>
                @forelse($callbacks['recent_logs'] as $log)
                    <tr>
                        <td>{{ $log['transaction_uid'] ?: 'n/a' }}</td>
                        <td>{{ $log['endpoint'] ?: 'n/a' }}</td>
                        <td><span class="status {{ in_array($log['result'], ['server_error', 'network_error', 'unknown'], true) ? 'bad' : '' }}">{{ $log['result'] }}</span></td>
                        <td>{{ $log['http_status'] ?: 'n/a' }}</td>
                        <td>{{ $log['duration_ms'] === null ? 'n/a' : $log['duration_ms'] . ' ms' }}</td>
                        <td>{{ $log['error_summary'] ?: 'n/a' }}</td>
                        <td>{{ $log['created_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No callback logs</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Callback Attempts</h2>
            <table>
                <thead><tr><th>Transaction</th><th>Type</th><th>Attempt</th><th>Endpoint</th><th>Result</th><th>Status</th><th>Duration</th><th>Error</th></tr></thead>
                <tbody>
                @forelse($callbacks['recent_attempts'] as $attempt)
                    <tr>
                        <td>{{ $attempt['transaction_uid'] ?: 'n/a' }}</td>
                        <td>{{ $attempt['type'] ?: 'n/a' }}</td>
                        <td>{{ $attempt['attempt_no'] ?: 'n/a' }}</td>
                        <td>{{ $attempt['endpoint'] ?: 'n/a' }}</td>
                        <td><span class="status {{ in_array($attempt['result'], ['failed', 'timeout', 'error'], true) ? 'bad' : '' }}">{{ $attempt['result'] ?: 'unknown' }}</span></td>
                        <td>{{ $attempt['http_status'] ?: 'n/a' }}</td>
                        <td>{{ $attempt['duration_ms'] === null ? 'n/a' : $attempt['duration_ms'] . ' ms' }}</td>
                        <td>{{ $attempt['error_summary'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No callback attempts</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'reports')
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
                <div class="value">{{ number_format((int) $summary['pending_settlements']) }}</div>
                <div class="label">Pending Settlements</div>
            </div>
        </section>

        <section class="panel section">
            <h2>Success Amounts</h2>
            <table>
                <thead><tr><th>Type</th><th>Currency</th><th>Amount</th><th>Count</th></tr></thead>
                <tbody>
                @forelse($wallet['success_amounts'] as $type => $currencies)
                    @foreach($currencies as $currency => $amount)
                        <tr>
                            <td>{{ $type }}</td>
                            <td>{{ $currency }}</td>
                            <td>{{ $amount['amount'] }}</td>
                            <td>{{ number_format((int) $amount['count']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="4" class="muted">No successful wallet amounts in this period</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Report Links</h2>
            <table>
                <thead><tr><th>Report</th><th>Path</th></tr></thead>
                <tbody>
                @foreach(['reports_summary' => 'Summary', 'transactions' => 'Transactions', 'reports_ggr' => 'GGR', 'settlements' => 'Settlements', 'reconciliation' => 'Reconciliation'] as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ isset($links[$key]) ? $links[$key] : 'n/a' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'support')
        <section class="grid">
            @forelse($support['by_status'] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} events</div>
                </div>
            @empty
                <div class="panel metric">
                    <div class="value">0</div>
                    <div class="label">Health Events</div>
                </div>
            @endforelse
            @forelse(isset($support['tickets_by_status']) ? $support['tickets_by_status'] : [] as $status => $row)
                <div class="panel metric">
                    <div class="value">{{ number_format((int) $row['count']) }}</div>
                    <div class="label">{{ $status }} tickets</div>
                </div>
            @empty
                <div class="panel metric">
                    <div class="value">0</div>
                    <div class="label">Support Tickets</div>
                </div>
            @endforelse
            <div class="panel metric">
                <div class="value">{{ number_format((int) $summary['open_reconciliation_items']) }}</div>
                <div class="label">Open Cases</div>
            </div>
        </section>

        <section class="panel section">
            <h2>Support Tickets</h2>
            <table>
                <thead><tr><th>Ticket</th><th>Status</th><th>Priority</th><th>Subject</th><th>Reference</th><th>Updated</th></tr></thead>
                <tbody>
                @forelse(isset($support['recent_tickets']) ? $support['recent_tickets'] : [] as $ticket)
                    <tr>
                        <td>{{ $ticket['ticket_uid'] ?: 'n/a' }}</td>
                        <td><span class="status {{ in_array($ticket['status'], ['open', 'in_progress'], true) ? 'warn' : '' }}">{{ $ticket['status'] ?: 'unknown' }}</span></td>
                        <td><span class="status {{ in_array($ticket['priority'], ['high', 'urgent'], true) ? 'bad' : '' }}">{{ $ticket['priority'] ?: 'normal' }}</span></td>
                        <td>{{ $ticket['subject'] ?: 'n/a' }}</td>
                        <td>{{ $ticket['external_reference'] ?: ($ticket['category'] ?: 'n/a') }}</td>
                        <td>{{ $ticket['last_message_at'] ?: ($ticket['created_at'] ?: 'n/a') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No support tickets</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Incidents</h2>
            <table>
                <thead><tr><th>Type</th><th>Status</th><th>Failures</th><th>Message</th><th>Created</th></tr></thead>
                <tbody>
                @forelse($support['recent_events'] as $event)
                    <tr>
                        <td>{{ $event['event_type'] ?: 'n/a' }}</td>
                        <td><span class="status {{ in_array($event['status'], ['failed', 'degraded', 'open'], true) ? 'bad' : '' }}">{{ $event['status'] ?: 'unknown' }}</span></td>
                        <td>{{ $event['failure_count'] === null ? 'n/a' : number_format((int) $event['failure_count']) }}</td>
                        <td>{{ $event['message'] ?: 'n/a' }}</td>
                        <td>{{ $event['created_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No support incidents</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Open Cases</h2>
            <table>
                <thead><tr><th>Transaction</th><th>State</th><th>Status</th><th>Priority</th><th>Reason</th><th>Detected</th></tr></thead>
                <tbody>
                @forelse($reconciliation['open_items'] as $item)
                    <tr>
                        <td>{{ $item['transaction_uid'] }}</td>
                        <td>{{ $item['state'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td><span class="status {{ $item['priority'] === 'high' ? 'bad' : '' }}">{{ $item['priority'] }}</span></td>
                        <td>{{ $item['reason'] }}</td>
                        <td>{{ $item['detected_at'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No open cases</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if($portal_section['key'] === 'docs')
        <section class="panel section">
            <h2>API Links</h2>
            <table>
                <thead><tr><th>Name</th><th>Path</th></tr></thead>
                <tbody>
                @foreach($links as $name => $path)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>{{ $path }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</main>
</body>
</html>
