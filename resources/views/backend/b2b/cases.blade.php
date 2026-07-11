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

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Support Ticket Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-support-ticket-uid">Ticket UID</label>
                                <input id="b2b-support-ticket-uid" type="text" name="ticket_uid" value="{{ old('ticket_uid') }}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-support-ticket-message">Message</label>
                                <textarea id="b2b-support-ticket-message" name="message" rows="4" class="form-control">{{ old('message') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label for="b2b-support-ticket-reason">Reason</label>
                                <textarea id="b2b-support-ticket-reason" name="reason" rows="4" class="form-control">{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" formaction="{{ route('backend.b2b.cases.support_ticket.comment') }}" class="btn btn-primary">
                                <i class="fa fa-comment"></i> Comment
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.cases.support_ticket.close') }}" class="btn btn-success">
                                <i class="fa fa-check"></i> Close
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.cases.support_ticket.reopen') }}" class="btn btn-default">
                                <i class="fa fa-undo"></i> Reopen
                            </button>
                            <hr>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'support_ticket.comment', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Comment Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'support_ticket.close', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Close Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'support_ticket.reopen', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
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
                                        <br><a href="{{ route('backend.b2b.cases.show', ['case_id' => $case->id]) }}">View Case</a>
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

        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Operator Support Tickets</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Operator</th>
                                <th>Status</th>
                                <th>Subject</th>
                                <th>Reference</th>
                                <th>Context</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($support_tickets as $ticket)
                                <tr>
                                    <td>
                                        <strong>{{ $ticket->ticket_uid }}</strong>
                                        <br><small class="text-muted">{{ $ticket->created_at ?: 'n/a' }}</small>
                                        <br><a href="{{ route('backend.b2b.cases.support_ticket.show', ['ticket_uid' => $ticket->ticket_uid]) }}">View Thread</a>
                                    </td>
                                    <td>
                                        {{ isset($ticket->operator_uid) && $ticket->operator_uid ? $ticket->operator_uid : ('operator ' . ($ticket->operator_id ?: 'n/a')) }}
                                        @if(isset($ticket->operator_name) && $ticket->operator_name)
                                            <br><small class="text-muted">{{ $ticket->operator_name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $ticket->status ?: 'open' }}
                                        <br><small class="text-muted">{{ $ticket->priority ?: 'normal' }}</small>
                                    </td>
                                    <td>
                                        {{ $ticket->subject ?: 'n/a' }}
                                        <br><small class="text-muted">
                                            {{ $ticket->category ?: 'uncategorized' }}
                                            / messages {{ isset($ticket->message_count) ? (int) $ticket->message_count : 0 }}
                                        </small>
                                    </td>
                                    <td>
                                        {{ $ticket->external_reference ?: 'n/a' }}
                                        <br><small class="text-muted">last {{ $ticket->last_message_at ?: 'n/a' }}</small>
                                    </td>
                                    <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 180px;">{{ $ticket->context_display ?: 'n/a' }}</pre></td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No operator support tickets</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
