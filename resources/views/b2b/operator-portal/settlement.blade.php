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
            <a href="{{ isset($links['portal_' . $key]) ? $links['portal_' . $key] : '#' }}" class="{{ $key === 'settlements' ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid">
        <div class="panel metric">
            <div class="value">{{ $detail['settlement']['settlement_uid'] ?: 'n/a' }}</div>
            <div class="label">Settlement</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['settlement']['status'] ?: 'unknown' }}</div>
            <div class="label">Status</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ $detail['settlement']['net_amount'] ?: '0.00000000' }} {{ $detail['settlement']['currency'] ?: '' }}</div>
            <div class="label">Net</div>
        </div>
        <div class="panel metric">
            <div class="value">{{ number_format(count($detail['by_type'])) }}</div>
            <div class="label">Breakdown Rows</div>
        </div>
    </section>

    <section class="panel section">
        <h2>Settlement Summary</h2>
        <table>
            <tbody>
            <tr><th>Period</th><td>{{ $detail['settlement']['period_start'] ?: 'n/a' }} - {{ $detail['settlement']['period_end'] ?: 'n/a' }}</td></tr>
            <tr><th>Currency</th><td>{{ $detail['settlement']['currency'] ?: 'n/a' }}</td></tr>
            <tr><th>Status</th><td><span class="status {{ in_array($detail['settlement']['status'], ['rejected'], true) ? 'bad' : (in_array($detail['settlement']['status'], ['submitted', 'exported'], true) ? 'warn' : '') }}">{{ $detail['settlement']['status'] ?: 'unknown' }}</span></td></tr>
            <tr><th>Bets</th><td>{{ $detail['settlement']['bets_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>Wins</th><td>{{ $detail['settlement']['wins_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>Refunds</th><td>{{ $detail['settlement']['refunds_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>GGR</th><td>{{ $detail['settlement']['ggr_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>Aggregator Fee</th><td>{{ $detail['settlement']['aggregator_fee_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>Provider Fee</th><td>{{ $detail['settlement']['provider_fee_amount'] ?: '0.00000000' }}</td></tr>
            <tr><th>Portal Detail Endpoint</th><td>{{ $detail['detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>JSON Report Detail</th><td>{{ $detail['report_detail_endpoint'] ?: 'n/a' }}</td></tr>
            <tr><th>Exported</th><td>{{ $detail['settlement']['exported_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Submitted</th><td>{{ $detail['settlement']['submitted_at'] ?: 'n/a' }} / {{ $detail['settlement']['submitted_by'] ?: 'n/a' }}</td></tr>
            <tr><th>Approved</th><td>{{ $detail['settlement']['approved_at'] ?: 'n/a' }} / {{ $detail['settlement']['approved_by'] ?: 'n/a' }}</td></tr>
            <tr><th>Rejected</th><td>{{ $detail['settlement']['rejected_at'] ?: 'n/a' }} / {{ $detail['settlement']['rejected_by'] ?: 'n/a' }}</td></tr>
            <tr><th>Created</th><td>{{ $detail['settlement']['created_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Updated</th><td>{{ $detail['settlement']['updated_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Settlement Totals</h2>
        <table>
            <thead><tr><th>Metric</th><th>Value</th></tr></thead>
            <tbody>
            @forelse($detail['totals'] as $metric => $amount)
                <tr>
                    <td>{{ $metric }}</td>
                    <td>{{ $amount }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No settlement totals</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Transaction Breakdown</h2>
        <table>
            <thead><tr><th>Type</th><th>Count</th><th>Amount</th></tr></thead>
            <tbody>
            @forelse($detail['by_type'] as $type => $row)
                <tr>
                    <td>{{ $type }}</td>
                    <td>{{ number_format((int) $row['count']) }}</td>
                    <td>{{ $row['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No settlement breakdown</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Approval Trail</h2>
        <table>
            <tbody>
            <tr><th>Decision</th><td>{{ $detail['approval']['decision'] ?: 'n/a' }}</td></tr>
            <tr><th>Actor</th><td>{{ $detail['approval']['actor'] ?: 'n/a' }}</td></tr>
            <tr><th>Reason</th><td>{{ $detail['approval']['reason'] ?: 'n/a' }}</td></tr>
            <tr><th>Decided</th><td>{{ $detail['approval']['decided_at'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="panel section">
        <h2>Export Metadata</h2>
        <table>
            <tbody>
            <tr><th>Format</th><td>{{ $detail['export']['format'] ?: 'n/a' }}</td></tr>
            <tr><th>SHA-256</th><td>{{ $detail['export']['sha256'] ?: 'n/a' }}</td></tr>
            <tr><th>Generated</th><td>{{ $detail['export']['generated_at'] ?: 'n/a' }}</td></tr>
            <tr><th>Snapshot Metadata</th><td>{{ $detail['metadata_summary'] ?: 'n/a' }}</td></tr>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
