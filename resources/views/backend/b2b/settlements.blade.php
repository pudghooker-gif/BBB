@extends('backend.layouts.app')

@section('page-title', 'B2B Settlements')
@section('page-heading', 'B2B Settlements')

@section('content')
    <section class="content-header">
        <h1>B2B Settlements</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Action</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-settlement-uid">Settlement UID</label>
                                <input
                                    id="b2b-settlement-uid"
                                    type="text"
                                    name="settlement_uid"
                                    value="{{ old('settlement_uid') }}"
                                    class="form-control"
                                    autocomplete="off"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="b2b-settlement-reason">Reason</label>
                                <textarea
                                    id="b2b-settlement-reason"
                                    name="reason"
                                    rows="4"
                                    class="form-control"
                                    required
                                >{{ old('reason') }}</textarea>
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
                        </div>
                    </form>
                </div>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Step-Up</h3>
                    </div>
                    <div class="box-body">
                        <p><a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.submit', 'redirect_to' => request()->getRequestUri()]) }}">Submit settlement</a></p>
                        <p><a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.approve', 'redirect_to' => request()->getRequestUri()]) }}">Approve settlement</a></p>
                        <p><a href="{{ route('backend.b2b.step_up.show', ['action' => 'settlement.reject', 'redirect_to' => request()->getRequestUri()]) }}">Reject settlement</a></p>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Cases</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Settlement</th>
                                <th>Operator</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Net</th>
                                <th>Decision</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($settlements as $settlement)
                                <tr>
                                    <td>
                                        <strong>{{ $settlement->settlement_uid }}</strong>
                                        <br><a href="{{ route('backend.b2b.settlements.show', ['settlement_uid' => $settlement->settlement_uid]) }}">View Settlement</a>
                                        @if($settlement->export_hash)
                                            <br><small class="text-muted">{{ $settlement->export_hash }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $settlement->operator_id }}</td>
                                    <td>
                                        {{ $settlement->period_start ?: 'n/a' }}
                                        <br><small class="text-muted">{{ $settlement->period_end ?: 'n/a' }}</small>
                                    </td>
                                    <td>{{ $settlement->status ?: 'n/a' }}</td>
                                    <td>{{ $settlement->net_amount }} {{ $settlement->currency }}</td>
                                    <td>
                                        @if($settlement->approved_by)
                                            {{ $settlement->approved_by }}
                                        @elseif($settlement->rejected_by)
                                            {{ $settlement->rejected_by }}
                                        @elseif($settlement->submitted_by)
                                            {{ $settlement->submitted_by }}
                                        @else
                                            n/a
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No settlement cases</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
