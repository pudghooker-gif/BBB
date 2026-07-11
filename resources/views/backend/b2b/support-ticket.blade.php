@extends('backend.layouts.app')

@section('page-title', 'B2B Support Ticket')
@section('page-heading', 'B2B Support Ticket')

@section('content')
    <section class="content-header">
        <h1>B2B Support Ticket</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Ticket Summary</h3>
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
                                <th style="width: 180px;">Ticket UID</th>
                                <td>{{ $ticket['ticket_uid'] }}</td>
                            </tr>
                            <tr>
                                <th>Operator</th>
                                <td>
                                    {{ $ticket['operator_uid'] ?: ('operator ' . ($ticket['operator_id'] ?: 'n/a')) }}
                                    @if($ticket['operator_name'])
                                        <br><small class="text-muted">{{ $ticket['operator_name'] }}</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>{{ $ticket['status'] ?: 'open' }} / {{ $ticket['priority'] ?: 'normal' }}</td>
                            </tr>
                            <tr>
                                <th>Subject</th>
                                <td>{{ $ticket['subject'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Reference</th>
                                <td>{{ $ticket['external_reference'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td>{{ $ticket['category'] ?: 'uncategorized' }}</td>
                            </tr>
                            <tr>
                                <th>Messages</th>
                                <td>{{ number_format((int) $ticket['message_count']) }}</td>
                            </tr>
                            <tr>
                                <th>Last Message</th>
                                <td>{{ $ticket['last_message_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Closed</th>
                                <td>{{ $ticket['closed_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td>{{ $ticket['created_at'] ?: 'n/a' }}</td>
                            </tr>
                            <tr>
                                <th>Context</th>
                                <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 220px;">{{ $ticket['context_display'] ?: 'n/a' }}</pre></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Ticket Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <input type="hidden" name="ticket_uid" value="{{ $ticket['ticket_uid'] }}">
                        <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-support-ticket-detail-message">Message</label>
                                <textarea id="b2b-support-ticket-detail-message" name="message" rows="4" class="form-control">{{ old('message') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label for="b2b-support-ticket-detail-reason">Reason</label>
                                <textarea id="b2b-support-ticket-detail-reason" name="reason" rows="4" class="form-control">{{ old('reason') }}</textarea>
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

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Message Thread</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Created</th>
                                <th>Actor</th>
                                <th>Source</th>
                                <th>Message</th>
                                <th>Metadata</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($ticket['messages'] as $message)
                                <tr>
                                    <td>{{ $message['created_at'] ?: 'n/a' }}</td>
                                    <td>{{ $message['actor'] ?: 'unknown' }}</td>
                                    <td>{{ $message['source'] ?: 'unknown' }}</td>
                                    <td>{{ $message['message'] ?: 'n/a' }}</td>
                                    <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 160px;">{{ is_array($message['metadata']) ? json_encode($message['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'n/a' }}</pre></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No support ticket messages</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
