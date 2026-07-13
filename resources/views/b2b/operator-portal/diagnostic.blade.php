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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'diagnostics' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ $detail['request']['request_uid'] ?: 'n/a' }}</div>
            <div class="label">Provider Request</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['request']['status'] ?: 'unknown' }}</div>
            <div class="label">Status</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['request']['action'] ?: 'n/a' }}</div>
            <div class="label">Action</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['request']['duration_ms'] === null ? 'n/a' : $detail['request']['duration_ms'] . ' ms' }}</div>
            <div class="label">Duration</div>
        </div>
    </section>

    <section class="panel section">
        <h2>Provider Request Summary</h2>
        <table>
            <tbody>
            <tr><th>Provider</th><td>{{ $detail['request']['provider'] ?: 'n/a' }}</td></tr>
            <tr><th>Game</th><td>{{ $detail['request']['game_uid'] ?: 'n/a' }}</td></tr>
            <tr><th>Session</th><td>{{ $detail['request']['session_id'] ?: 'n/a' }}</td></tr>
            <tr><th>Status</th><td><span class="status {{ in_array($detail['request']['status'], ['failed', 'timeout', 'error'], true) ? 'bad' : '' }}">{{ $detail['request']['status'] ?: 'unknown' }}</span></td></tr>
            <tr><th>Error</th><td>{{ $detail['request']['error_summary'] ?: 'n/a' }}</td></tr>
            <tr><th>Portal Detail Endpoint</th><td>{{ $detail['detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Session Detail Endpoint</th><td>{{ $detail['session_detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Created</th><td>{{ $detail['request']['created_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Updated</th><td>{{ $detail['request']['updated_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Request Summary</h2>
        <table>
            <tbody>
            <tr><td>{{ $detail['request_summary'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Response Summary</h2>
        <table>
            <tbody>
            <tr><td>{{ $detail['response_summary'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
