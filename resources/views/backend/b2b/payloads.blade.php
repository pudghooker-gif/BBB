@extends('backend.layouts.app')

@section('page-title', 'B2B Payload Review')
@section('page-heading', 'B2B Payload Review')

@section('content')
    <section class="content-header">
        <h1>B2B Payload Review</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        @if($success)
            <div class="alert alert-success">{{ $success }}</div>
        @endif

        <div class="row">
            <div class="col-md-4">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Raw Payload Access</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.payloads.raw') }}">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-payload-attempt">Attempt ID</label>
                                <input id="b2b-payload-attempt" type="number" min="1" name="attempt_id" value="{{ old('attempt_id') }}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-payload-reason">Reason</label>
                                <textarea id="b2b-payload-reason" name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-eye"></i> View Raw Payload
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'payload.view_raw', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Step-Up
                            </a>
                        </div>
                    </form>
                </div>

                @if($rawAttempt)
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title">Raw Attempt #{{ $rawAttempt->id }}</h3>
                        </div>
                        <div class="box-body">
                            <p>
                                <strong>{{ $rawAttempt->transaction_uid ?: 'n/a' }}</strong>
                                <br><small class="text-muted">{{ $rawAttempt->type ?: 'n/a' }} / {{ $rawAttempt->result ?: 'n/a' }}</small>
                            </p>
                            <h4>Request</h4>
                            <pre style="white-space: pre-wrap; word-break: break-word;">{{ $rawAttempt->request_body_display ?: 'n/a' }}</pre>
                            <h4>Response</h4>
                            <pre style="white-space: pre-wrap; word-break: break-word;">{{ $rawAttempt->response_body_display ?: 'n/a' }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Recent Wallet Attempts</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Attempt</th>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Request</th>
                                <th>Response</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($attempts as $attempt)
                                <tr>
                                    <td>
                                        #{{ $attempt->id }}
                                        <br><small class="text-muted">try {{ (int) $attempt->attempt_no }}</small>
                                    </td>
                                    <td>{{ $attempt->transaction_uid ?: 'n/a' }}</td>
                                    <td>{{ $attempt->type ?: 'n/a' }}</td>
                                    <td>
                                        {{ $attempt->result ?: 'n/a' }}
                                        <br><small class="text-muted">{{ $attempt->http_status ?: 'n/a' }} / {{ $attempt->duration_ms ?: 'n/a' }} ms</small>
                                    </td>
                                    <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 180px;">{{ $attempt->request_body_display ?: 'n/a' }}</pre></td>
                                    <td><pre style="white-space: pre-wrap; word-break: break-word; max-height: 180px;">{{ $attempt->response_body_display ?: 'n/a' }}</pre></td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No wallet attempts</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
