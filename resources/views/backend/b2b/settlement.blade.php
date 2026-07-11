@extends('backend.layouts.app')

@section('page-title', 'B2B Settlement Detail')
@section('page-heading', 'B2B Settlement Detail')

@section('content')
    <section class="content-header">
        <h1>B2B Settlement Detail</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Summary</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('backend.b2b.settlements.index') }}" class="btn btn-box-tool">
                                <i class="fa fa-arrow-left"></i> Back to Settlements
                            </a>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <th style="width: 180px;">Settlement UID</th>
                                <td>{{ $settlement['settlement_uid'] }}</td>
                            </tr>
                            <tr>
                                <th>Operator</th>
                                <td>operator {{ $settlement['operator_id'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Period</th>
                                <td>{{ $settlement['period_start'] ?: 'n/a' }} - {{ $settlement['period_end'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>{{ $settlement['status'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Currency</th>
                                <td>{{ $settlement['currency'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Amounts</th>
                                <td>
                                    bets {{ $settlement['bets_amount'] }}
                                    / wins {{ $settlement['wins_amount'] }}
                                    / refunds {{ $settlement['refunds_amount'] }}
                                    / ggr {{ $settlement['ggr_amount'] }}
                                    / net {{ $settlement['net_amount'] }}
                                </td>
                            </tr>
                            <tr>
                                <th>Fees</th>
                                <td>aggregator {{ $settlement['aggregator_fee_amount'] }} / provider {{ $settlement['provider_fee_amount'] }}</td>
                            </tr>
                            <tr>
                                <th>Export</th>
                                <td>
                                    {{ $settlement['export_format'] ?: 'n/a' }}
                                    @if($settlement['export_hash'])
                                        <br><small class="text-muted">{{ $settlement['export_hash'] }}</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Submitted</th>
                                <td>{{ $settlement['submitted_at'] ?: 'n/a' }} / {{ $settlement['submitted_by'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Approved</th>
                                <td>{{ $settlement['approved_at'] ?: 'n/a' }} / {{ $settlement['approved_by'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Rejected</th>
                                <td>{{ $settlement['rejected_at'] ?: 'n/a' }} / {{ $settlement['rejected_by'] ?: 'n/a' }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <input type="hidden" name="settlement_uid" value="{{ $settlement['settlement_uid'] }}">
                        <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-settlement-detail-reason">Reason</label>
                                <textarea id="b2b-settlement-detail-reason" name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" formaction="{{ route('backend.b2b.settlements.submit') }}" class="btn btn-primary">
                                <i class="fa fa-upload"></i> Submit
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.settlements.approve') }}" class="btn btn-success">
                                <i class="fa fa-check"></i> Approve
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.settlements.reject') }}" class="btn btn-danger">
                                <i class="fa fa-times"></i> Reject
                            </button>
                            <hr>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.submit', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Submit Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.approve', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Approve Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.reject', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Reject Step-Up
                            </a>
                        </div>
                    </form>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Totals</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($settlement['totals'] as $metric => $amount)
                                <tr>
                                    <td>{{ $metric }}</td>
                                    <td>{{ $amount }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">No settlement totals</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Transaction Breakdown</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th>Count</th>
                                <th>Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($settlement['by_type'] as $type => $row)
                                <tr>
                                    <td>{{ $type }}</td>
                                    <td>{{ isset($row['count']) ? (int) $row['count'] : 0 }}</td>
                                    <td>{{ isset($row['amount']) ? $row['amount'] : '0.00000000' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">No settlement breakdown</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Approval Trail</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <th style="width: 180px;">Decision</th>
                                <td>{{ isset($settlement['approval']['decision']) ? $settlement['approval']['decision'] : 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Actor</th>
                                <td>{{ isset($settlement['approval']['actor']) ? $settlement['approval']['actor'] : 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Reason</th>
                                <td>{{ isset($settlement['approval']['reason']) ? $settlement['approval']['reason'] : 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Decided</th>
                                <td>{{ isset($settlement['approval']['decided_at']) ? $settlement['approval']['decided_at'] : 'n/a' }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Snapshot Metadata</h3>
                    </div>
                    <div class="box-body">
                        <pre style="white-space: pre-wrap; word-break: break-word; max-height: 260px;">{{ $settlement['snapshot_display'] ?: 'n/a' }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
