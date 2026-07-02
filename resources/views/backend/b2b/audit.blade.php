@extends('backend.layouts.app')

@section('page-title', 'B2B Audit Trail')
@section('page-heading', 'B2B Audit Trail')

@section('content')
    <section class="content-header">
        <h1>B2B Audit Trail</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Filters</h3>
            </div>
            <form method="get" action="{{ route('backend.b2b.audit.index') }}">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-operator">Operator UID</label>
                                <input id="b2b-audit-operator" type="text" name="operator_uid" value="{{ $filters['operator_uid'] }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-event">Event Type</label>
                                <input id="b2b-audit-event" type="text" name="event_type" value="{{ $filters['event_type'] }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-actor">Actor</label>
                                <input id="b2b-audit-actor" type="text" name="actor" value="{{ $filters['actor'] }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-subject">Subject ID</label>
                                <input id="b2b-audit-subject" type="text" name="subject_id" value="{{ $filters['subject_id'] }}" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-from">From</label>
                                <input id="b2b-audit-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b2b-audit-to">To</label>
                                <input id="b2b-audit-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="b2b-audit-limit">Limit</label>
                                <input id="b2b-audit-limit" type="number" min="1" max="200" name="limit" value="{{ $limit }}" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-filter"></i> Apply
                                    </button>
                                    <a href="{{ route('backend.b2b.audit.index') }}" class="btn btn-default">
                                        <i class="fa fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Audit Events</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>Operator</th>
                        <th>Subject</th>
                        <th>Actor</th>
                        <th>Reason</th>
                        <th>Request</th>
                        <th>Metadata</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>
                                <strong>{{ $event->event_type }}</strong>
                                <br><small class="text-muted">#{{ $event->id }} / {{ $event->created_at ?: 'n/a' }}</small>
                            </td>
                            <td>
                                {{ $event->operator_uid ?: ('operator ' . ($event->operator_id ?: 'n/a')) }}
                                <br><small class="text-muted">{{ $event->operator_name ?: 'n/a' }}</small>
                            </td>
                            <td>
                                {{ $event->subject_type ?: 'n/a' }}
                                <br><small class="text-muted">{{ $event->subject_id ?: 'n/a' }}</small>
                            </td>
                            <td>{{ $event->actor ?: 'n/a' }}</td>
                            <td>{{ $event->reason_display ?: 'n/a' }}</td>
                            <td>
                                {{ $event->ip_address ?: 'n/a' }}
                                <br><small class="text-muted">{{ $event->user_agent ?: 'n/a' }}</small>
                            </td>
                            <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 180px;">{{ $event->metadata_display ?: 'n/a' }}</pre></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No B2B audit events</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
