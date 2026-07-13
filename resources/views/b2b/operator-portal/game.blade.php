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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'games' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ $detail['game']['game_uid'] ?: 'n/a' }}</div>
            <div class="label">Game</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['game']['status'] ?: 'unknown' }}</div>
            <div class="label">Catalog Status</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['game']['provider'] ?: 'n/a' }}</div>
            <div class="label">Provider</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format((int) $detail['session_summary']['count']) }}</div>
            <div class="label">Sessions</div>
        </div>
    </section>

    <section class="panel section">
        <h2>Game Summary</h2>
        <table>
            <tbody>
            <tr><th>Title</th><td>{{ $detail['game']['title'] ?: 'n/a' }}</td></tr>
            <tr><th>Provider</th><td>{{ $detail['game']['provider'] ?: 'n/a' }}</td></tr>
            <tr><th>Category</th><td>{{ $detail['game']['category'] ?: 'n/a' }}</td></tr>
            <tr><th>Source</th><td>{{ $detail['game']['source'] ?: 'n/a' }}</td></tr>
            <tr><th>RTP</th><td>{{ $detail['game']['rtp'] ?: 'n/a' }}</td></tr>
            <tr><th>Volatility</th><td>{{ $detail['game']['volatility'] ?: 'n/a' }}</td></tr>
            <tr><th>Thumbnail</th><td>{{ $detail['game']['thumbnail_url'] ?: 'n/a' }}</td></tr>
            <tr><th>Demo Supported</th><td>{{ $detail['game']['demo_supported'] === null ? 'n/a' : ($detail['game']['demo_supported'] ? 'yes' : 'no') }}</td></tr>
            <tr><th>Real Supported</th><td>{{ $detail['game']['real_supported'] === null ? 'n/a' : ($detail['game']['real_supported'] ? 'yes' : 'no') }}</td></tr>
            <tr><th>Supported Currencies</th><td>{{ count($detail['game']['supported_currencies']) ? implode(', ', $detail['game']['supported_currencies']) : 'all' }}</td></tr>
            <tr><th>Supported Countries</th><td>{{ count($detail['game']['supported_countries']) ? implode(', ', $detail['game']['supported_countries']) : 'all' }}</td></tr>
            <tr><th>Portal Detail Endpoint</th><td>{{ $detail['detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>JSON API Detail</th><td>{{ $detail['api_detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Metadata Summary</th><td>{{ $detail['game']['metadata_summary'] ?: 'n/a' }}</td></tr>
            <tr><th>Created</th><td>{{ $detail['game']['created_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Updated</th><td>{{ $detail['game']['updated_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Assignment</h2>
        @if($detail['assignment'])
            <table>
                <tbody>
                <tr><th>Status</th><td><span class="status {{ $detail['assignment']['status'] === 'blocked' ? 'bad' : ($detail['assignment']['status'] === 'disabled' ? 'warn' : '') }}">{{ $detail['assignment']['status'] ?: 'unknown' }}</span></td></tr>
                <tr><th>Provider</th><td>{{ $detail['assignment']['provider'] ?: 'n/a' }}</td></tr>
                <tr><th>Demo Enabled</th><td>{{ $detail['assignment']['demo_enabled'] ? 'yes' : 'no' }}</td></tr>
                <tr><th>Real Enabled</th><td>{{ $detail['assignment']['real_enabled'] ? 'yes' : 'no' }}</td></tr>
                <tr><th>Allowed Currencies</th><td>{{ count($detail['assignment']['allowed_currencies']) ? implode(', ', $detail['assignment']['allowed_currencies']) : 'all' }}</td></tr>
                <tr><th>Allowed Countries</th><td>{{ count($detail['assignment']['allowed_countries']) ? implode(', ', $detail['assignment']['allowed_countries']) : 'all' }}</td></tr>
                <tr><th>Metadata Summary</th><td>{{ $detail['assignment']['metadata_summary'] ?: 'n/a' }}</td></tr>
                <tr><th>Created</th><td>{{ $detail['assignment']['created_at'] ?: 'n/a' }}</td></tr>
                <tr><th>Updated</th><td>{{ $detail['assignment']['updated_at'] ?: 'n/a' }}</td></tr>
                </tbody>
            </table>
        @else
            <table><tbody><tr><td class="muted">No assignment</td></tr></tbody></table>
        @endif
    </section>

    <section class="panel section">
        <h2>Availability</h2>
        <table>
            <thead><tr><th>Mode</th><th>Status</th><th>Currency</th><th>Provider</th><th>Source</th><th>Code</th><th>Message</th></tr></thead>
            <tbody>
            @foreach($detail['availability'] as $row)
                <tr>
                    <td>{{ $row['mode'] }}</td>
                    <td><span class="status {{ $row['ok'] ? '' : 'bad' }}">{{ $row['ok'] ? 'available' : 'unavailable' }}</span></td>
                    <td>{{ $row['currency'] ?: 'n/a' }}</td>
                    <td>{{ $row['provider'] ?: 'n/a' }}</td>
                    <td>{{ $row['source'] ?: 'n/a' }}</td>
                    <td>{{ $row['code'] ?: 'n/a' }}</td>
                    <td>{{ $row['message'] ?: 'n/a' }}</td>
                </tr>
            @endforeach
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
        <h2>Recent Sessions</h2>
        <table>
            <thead><tr><th>Session</th><th>Player</th><th>Mode</th><th>Status</th><th>Currency</th><th>Detail Endpoint</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($detail['recent_sessions'] as $session)
                <tr>
                    <td>{{ $session['session_uid'] ?: 'n/a' }}</td>
                    <td>{{ $session['external_player_id'] ?: 'n/a' }}</td>
                    <td>{{ $session['mode'] ?: 'n/a' }}</td>
                    <td><span class="status {{ in_array($session['status'], ['failed', 'stale', 'expired'], true) ? 'bad' : ($session['status'] === 'closed' ? 'warn' : '') }}">{{ $session['status'] ?: 'unknown' }}</span></td>
                    <td>{{ $session['currency'] ?: 'n/a' }}</td>
                    <td>{{ $session['detail_endpoint'] ?: 'n/a' }}</td>
                    <td>{{ $session['created_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No sessions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Recent Transactions</h2>
        <table>
            <thead><tr><th>Transaction</th><th>Type</th><th>Status</th><th>Amount</th><th>Session</th><th>Round</th><th>Detail Endpoint</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($detail['recent_transactions'] as $transaction)
                <tr>
                    <td>{{ $transaction['transaction_uid'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['type'] ?: 'n/a' }}</td>
                    <td><span class="status {{ in_array($transaction['status'], ['failed', 'timeout', 'unknown', 'manual_review', 'dead_letter', 'rollback_required'], true) ? 'bad' : ($transaction['status'] === 'pending' ? 'warn' : '') }}">{{ $transaction['status'] ?: 'unknown' }}</span></td>
                    <td>{{ $transaction['amount'] ?: '0.00000000' }} {{ $transaction['currency'] ?: '' }}</td>
                    <td>{{ $transaction['session_id'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['round_id'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['detail_endpoint'] ?: 'n/a' }}</td>
                    <td>{{ $transaction['created_at'] ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No transactions</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
