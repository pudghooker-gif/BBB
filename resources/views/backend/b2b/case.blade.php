@extends('backend.layouts.app')

@section('page-title', 'B2B Case Detail')
@section('page-heading', 'B2B Case Detail')

@section('content')
    <section class="content-header">
        <h1>B2B Case Detail</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Case Summary</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('backend.b2b.cases.index') }}" class="btn btn-box-tool">
                                <i class="fa fa-arrow-left"></i> Back to Cases
                            </a>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <th style="width: 180px;">Case ID</th>
                                <td>#{{ $case['id'] }}</td>
                            </tr>
                            <tr>
                                <th>Operator</th>
                                <td>
                                    {{ $case['operator_uid'] ?: ('operator ' . ($case['operator_id'] ?: 'n/a')) }}
                                    @if($case['operator_name'])
                                        <br><small class="text-muted">{{ $case['operator_name'] }}</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Transaction</th>
                                <td>
                                    {{ $case['transaction_uid'] ?: ('wallet transaction ' . ($case['wallet_transaction_id'] ?: 'n/a')) }}
                                    <br><small class="text-muted">
                                        {{ $case['transaction_type'] ?: 'n/a' }}
                                        / {{ $case['transaction_status'] ?: $case['status'] ?: 'n/a' }}
                                        / attempts {{ $case['transaction_attempts'] === null ? 0 : (int) $case['transaction_attempts'] }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <th>Amount</th>
                                <td>{{ $case['transaction_amount'] ?: 'n/a' }} {{ $case['transaction_currency'] ?: '' }}</td>
                            </tr>
                            <tr>
                                <th>State</th>
                                <td>{{ $case['state'] ?: 'open' }} / {{ $case['priority'] ?: 'normal' }}</td>
                            </tr>
                            <tr>
                                <th>Reason</th>
                                <td>{{ $case['reason'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Wallet Error</th>
                                <td>{{ $case['transaction_last_error'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Operator Comments</th>
                                <td>{{ number_format((int) $case['operator_comment_count']) }}</td>
                            </tr>
                            <tr>
                                <th>Detected</th>
                                <td>{{ $case['detected_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Resolved</th>
                                <td>{{ $case['resolved_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Updated</th>
                                <td>{{ $case['updated_at'] ?: $case['created_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Context</th>
                                <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 220px;">{{ $case['context_display'] ?: 'n/a' }}</pre></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $caseState = isset($case['state']) && $case['state'] ? $case['state'] : 'open';
                    $canClaim = in_array($caseState, ['open', 'in_progress'], true);
                    $canResolve = in_array($caseState, ['open', 'in_progress'], true);
                    $canReopen = $caseState === 'resolved';
                @endphp

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Case Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <input type="hidden" name="case_id" value="{{ $case['id'] }}">
                        <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                        <div class="box-body">
                            @if($canClaim || $canResolve || $canReopen)
                                <div class="form-group">
                                    <label for="b2b-case-detail-reason">{{ $canReopen ? 'Reopen Reason' : 'Reason' }}</label>
                                    <textarea id="b2b-case-detail-reason" name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                                </div>
                            @else
                                <p class="text-muted">No lifecycle staff actions are available for this case state.</p>
                            @endif
                        </div>
                        <div class="box-footer">
                            @if($canClaim)
                                <button type="submit" formaction="{{ route('backend.b2b.cases.claim') }}" class="btn btn-warning">
                                    <i class="fa fa-user-plus"></i> Claim
                                </button>
                            @endif
                            @if($canResolve)
                                <button type="submit" formaction="{{ route('backend.b2b.cases.resolve') }}" class="btn btn-success">
                                    <i class="fa fa-check-circle"></i> Resolve
                                </button>
                            @endif
                            @if($canReopen)
                                <button type="submit" formaction="{{ route('backend.b2b.cases.reopen') }}" class="btn btn-default">
                                    <i class="fa fa-undo"></i> Reopen
                                </button>
                            @endif
                            @if($case['transaction_uid'])
                                <a href="{{ route('backend.b2b.wallet_manual_actions.index', [
                                    'transaction_uid' => $case['transaction_uid'],
                                    'operator_id' => $case['operator_id'],
                                    'action' => 'mark-review',
                                    'reason' => 'Manual review follow-up for B2B case #' . $case['id'] . '.',
                                    'redirect_to' => request()->getRequestUri(),
                                ]) }}" class="btn btn-danger">
                                    <i class="fa fa-gavel"></i> Manual Wallet Action
                                </a>
                            @endif
                            <hr>
                            @if($canClaim)
                                <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.claim', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                    <i class="fa fa-shield"></i> Claim Step-Up
                                </a>
                            @endif
                            @if($canResolve)
                                <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.resolve', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                    <i class="fa fa-shield"></i> Resolve Step-Up
                                </a>
                            @endif
                            @if($canReopen)
                                <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.reopen', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                    <i class="fa fa-shield"></i> Reopen Step-Up
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Operator Comments</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Created</th>
                                <th>Actor</th>
                                <th>Source</th>
                                <th>Message</th>
                                <th>Reference</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($case['operator_comments'] as $comment)
                                <tr>
                                    <td>{{ $comment['created_at'] ?: 'n/a' }}</td>
                                    <td>{{ $comment['actor'] ?: 'unknown' }}</td>
                                    <td>{{ $comment['source'] ?: 'unknown' }}</td>
                                    <td>{{ $comment['message'] ?: 'n/a' }}</td>
                                    <td>{{ $comment['external_reference'] ?: 'n/a' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No operator comments</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Case Events</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Created</th>
                                <th>Action</th>
                                <th>State</th>
                                <th>Actor</th>
                                <th>Reason</th>
                                <th>Step-Up</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($case['case_events'] as $event)
                                <tr>
                                    <td>{{ $event['created_at'] ?: 'n/a' }}</td>
                                    <td>{{ $event['action'] ?: 'n/a' }}</td>
                                    <td>{{ $event['state'] ?: 'n/a' }}</td>
                                    <td>
                                        {{ $event['actor'] ?: 'unknown' }}
                                        @if($event['source'])
                                            <br><small class="text-muted">{{ $event['source'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $event['reason'] ?: 'n/a' }}
                                        @if($event['permission'])
                                            <br><small class="text-muted">{{ $event['permission'] }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $event['step_up'] ? 'yes' : 'no' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No case events</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
