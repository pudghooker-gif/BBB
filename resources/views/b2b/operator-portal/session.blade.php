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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'sessions' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ $detail['session']['session_uid'] ?: 'n/a' }}</div>
            <div class="label">Session</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['session']['status'] ?: 'unknown' }}</div>
            <div class="label">Status</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['session']['game_uid'] ?: 'n/a' }}</div>
            <div class="label">Game</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format(count($detail['transactions'])) }}</div>
            <div class="label">Transactions</div>
        </div>
    </section>

    <section class="panel section">
        <h2>Session Summary</h2>
        <table>
            <tbody>
            <tr><th>Player</th><td>{{ $detail['session']['external_player_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Provider</th><td>{{ $detail['session']['provider'] ?: 'n/a' }}</td></tr>
            <tr><th>Mode</th><td>{{ $detail['session']['mode'] ?: 'n/a' }}</td></tr>
            <tr><th>Currency</th><td>{{ $detail['session']['currency'] ?: 'n/a' }}</td></tr>
            <tr><th>Language</th><td>{{ $detail['session']['language'] ?: 'n/a' }}</td></tr>
            <tr><th>Country</th><td>{{ $detail['session']['country'] ?: 'n/a' }}</td></tr>
            <tr><th>Status</th><td><span class="status {{ in_array($detail['session']['status'], ['failed', 'stale', 'expired'], true) ? 'bad' : ($detail['session']['status'] === 'closed' ? 'warn' : '') }}">{{ $detail['session']['status'] ?: 'unknown' }}</span></td></tr>
            <tr><th>Close Reason</th><td>{{ $detail['session']['close_reason'] ?: 'n/a' }}</td></tr>
            <tr><th>Failure</th><td>{{ $detail['session']['failure_code'] ?: 'n/a' }} {{ $detail['session']['failure_message'] ?: '' }}</td></tr>
            <tr><th>Portal Detail Endpoint</th><td>{{ $detail['detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>JSON API Detail</th><td>{{ $detail['api_detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Heartbeat Timeout</th><td>{{ $detail['session']['heartbeat_timeout_seconds'] !== null ? $detail['session']['heartbeat_timeout_seconds'] . ' seconds' : 'n/a' }}</td></tr>
            <tr><th>Last Seen</th><td>{{ $detail['session']['last_seen_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Heartbeat</th><td>{{ $detail['session']['heartbeat_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Stale</th><td>{{ $detail['session']['stale_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Expires</th><td>{{ $detail['session']['expires_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Closed</th><td>{{ $detail['session']['closed_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Created</th><td>{{ $detail['session']['created_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Updated</th><td>{{ $detail['session']['updated_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Successful Amounts</h2>
        <table>
            <thead><tr><th>Type</th><th>Currency</th><th>Amount</th><th>Count</th></tr></thead>
            <tbody>
            @forelse($detail['transaction_summary']['success_amounts'] as $type => $currencies)
                @foreach($currencies as $currency => $row)
                    <tr>
                        <td>{{ $type }}</td>
                        <td>{{ $currency }}</td>
                        <td>{{ $row['amount'] }}</td>
                        <td>{{ number_format((int) $row['count']) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="4" class="muted">No successful amounts</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Session Transactions</h2>
        <table>
            <thead><tr><th>Transaction</th><th>Type</th><th>Status</th><th>Amount</th><th>Round</th><th>Attempts</th><th>Detail Endpoint</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($detail['transactions'] as $transaction)
                <tr>
                    <td>{{ $transaction['transaction_uid'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['type'] ?: 'n/a' }}</td>
                    <td><span class="status {{ in_array($transaction['status'], ['failed', 'timeout', 'unknown', 'manual_review', 'dead_letter', 'rollback_required'], true) ? 'bad' : ($transaction['status'] === 'pending' ? 'warn' : '') }}">{{ $transaction['status'] ?: 'unknown' }}</span></td>
                    <td>{{ $transaction['amount'] ?: '0.00000000' }} {{ $transaction['currency'] ?: '' }}</td>
                    <td>{{ $transaction['round_id'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['attempts'] !== null ? number_format((int) $transaction['attempts']) : 'n/a' }}</td>
                    <td>{{ $transaction['detail_endpoint'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['created_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No session transactions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
