@extends('backend.layouts.app')

@section('page-title', 'B2B Case Management')
@section('page-heading', 'B2B Case Management')

@section('content')
    <section class="content-header">
        <h1>B2B Case Management</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Case Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-case-id">Case ID</label>
                                <input id="b2b-case-id" type="number" min="1" name="case_id" value="{{ old('case_id') }}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-case-reason">Reason</label>
                                <textarea id="b2b-case-reason" name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" formaction="{{ route('backend.b2b.cases.claim') }}" class="btn btn-warning">
                                <i class="fa fa-user-plus"></i> Claim
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.cases.resolve') }}" class="btn btn-success">
                                <i class="fa fa-check-circle"></i> Resolve
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.cases.reopen') }}" class="btn btn-default">
                                <i class="fa fa-undo"></i> Reopen
                            </button>
                            <hr>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.claim', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Claim Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.resolve', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Resolve Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'case.reopen', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Reopen Step-Up
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Reconciliation Cases</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Case</th>
                                <th>Transaction</th>
                                <th>State</th>
                                <th>Reason</th>
                                <th>Wallet</th>
                                <th>Context</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($cases as $case)
                                <tr>
                                    <td>
                                        #{{ $case->id }}
                                        <br><small class="text-muted">{{ $case->detected_at ?: $case->created_at ?: 'n/a' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $case->transaction_uid ?: $case->wallet_transaction_id ?: 'n/a' }}</strong>
                                        <br><small class="text-muted">operator {{ $case->operator_id ?: 'n/a' }}</small>
                                    </td>
                                    <td>
                                        {{ $case->state ?: 'open' }}
                                        <br><small class="text-muted">{{ $case->priority ?: 'normal' }}</small>
                                    </td>
                                    <td>
                                        {{ $case->reason ?: 'n/a' }}
                                        <br><small class="text-muted">{{ $case->status ?: 'n/a' }}</small>
                                    </td>
                                    <td>
                                        {{ isset($case->transaction_type) ? ($case->transaction_type ?: 'n/a') : 'n/a' }}
                                        <br><small class="text-muted">
                                            {{ isset($case->transaction_amount) ? $case->transaction_amount : 'n/a' }}
                                            {{ isset($case->transaction_currency) ? $case->transaction_currency : '' }}
                                            / attempts {{ isset($case->transaction_attempts) ? (int) $case->transaction_attempts : 0 }}
                                        </small>
                                        @if(isset($case->transaction_last_error) && $case->transaction_last_error)
                                            <br><small class="text-muted">{{ $case->transaction_last_error }}</small>
                                        @endif
                                    </td>
                                    <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 180px;">{{ $case->context_display ?: 'n/a' }}</pre></td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No B2B cases</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
