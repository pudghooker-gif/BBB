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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'support' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($thread_type === 'case')
        <section class="grid">
            <div class="panel metric">
                <div class="value">{{ $thread['transaction_uid'] }}</div>
                <div class="label">Transaction</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ $thread['state'] ?: 'unknown' }}</div>
                <div class="label">State</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ $thread['priority'] ?: 'normal' }}</div>
                <div class="label">Priority</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ number_format((int) $thread['comment_count']) }}</div>
                <div class="label">Comments</div>
            </div>
        </section>

        <section class="panel section">
            <h2>Case Summary</h2>
            <table>
                <tbody>
                <tr><th>Status</th><td><span class="status {{ in_array($thread['state'], ['open', 'in_progress'], true) ? 'warn' : '' }}">{{ $thread['status'] ?: 'unknown' }}</span></td></tr>
                <tr><th>Reason</th><td>{{ $thread['reason'] ?: 'n/a' }}</td></tr>
                <tr><th>API Detail Endpoint</th><td>{{ $detail_endpoint }}</td></tr>
                <tr><th>Detected</th><td>{{ $thread['detected_at'] ?: 'n/a' }}</td></tr>
                <tr><th>Resolved</th><td>{{ $thread['resolved_at'] ?: 'n/a' }}</td></tr>
                <tr><th>Updated</th><td>{{ $thread['updated_at'] ?: 'n/a' }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Case Comments</h2>
            <table>
                <thead><tr><th>Created</th><th>Actor</th><th>Source</th><th>Reference</th><th>Message</th></tr></thead>
                <tbody>
                @forelse($thread['comments'] as $comment)
                    <tr>
                        <td>{{ $comment['created_at'] ?: 'n/a' }}</td>
                        <td>{{ $comment['actor'] ?: 'unknown' }}</td>
                        <td>{{ $comment['source'] ?: 'unknown' }}</td>
                        <td>{{ $comment['external_reference'] ?: 'n/a' }}</td>
                        <td>{{ $comment['message'] ?: 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No operator-visible comments</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @else
        <section class="grid">
            <div class="panel metric">
                <div class="value">{{ $thread['ticket_uid'] }}</div>
                <div class="label">Ticket</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ $thread['status'] ?: 'unknown' }}</div>
                <div class="label">Status</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ $thread['priority'] ?: 'normal' }}</div>
                <div class="label">Priority</div>
            </div>
            <div class="panel metric">
                <div class="value">{{ number_format((int) $thread['message_count']) }}</div>
                <div class="label">Messages</div>
            </div>
        </section>

        <section class="panel section">
            <h2>Ticket Summary</h2>
            <table>
                <tbody>
                <tr><th>Subject</th><td>{{ $thread['subject'] ?: 'n/a' }}</td></tr>
                <tr><th>Category</th><td>{{ $thread['category'] ?: 'n/a' }}</td></tr>
                <tr><th>Reference</th><td>{{ $thread['external_reference'] ?: 'n/a' }}</td></tr>
                <tr><th>API Detail Endpoint</th><td>{{ $detail_endpoint }}</td></tr>
                <tr><th>Last Message</th><td>{{ $thread['last_message_at'] ?: 'n/a' }}</td></tr>
                <tr><th>Closed</th><td>{{ $thread['closed_at'] ?: 'n/a' }}</td></tr>
                <tr><th>Created</th><td>{{ $thread['created_at'] ?: 'n/a' }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="panel section">
            <h2>Ticket Messages</h2>
            <table>
                <thead><tr><th>Created</th><th>Actor</th><th>Source</th><th>Message</th><th>Metadata</th></tr></thead>
                <tbody>
                @forelse($thread['messages'] as $message)
                    <tr>
                        <td>{{ $message['created_at'] ?: 'n/a' }}</td>
                        <td>{{ $message['actor'] ?: 'unknown' }}</td>
                        <td>{{ $message['source'] ?: 'unknown' }}</td>
                        <td>{{ $message['message'] ?: 'n/a' }}</td>
                        <td>{{ is_array($message['metadata']) ? json_encode($message['metadata'], JSON_UNESCAPED_SLASHES) : 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No ticket messages</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif
</main>
</body>
</html>
