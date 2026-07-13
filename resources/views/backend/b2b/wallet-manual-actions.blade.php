@extends('backend.layouts.app')

@section('page-title', 'B2B Manual Wallet Actions')
@section('page-heading', 'B2B Manual Wallet Actions')

@section('content')
    <section class="content-header">
        <h1>B2B Manual Wallet Actions</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Apply Action</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.wallet_manual_actions.store') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ old('redirect_to', $form['redirect_to']) }}">
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-wallet-transaction">Transaction UID / ID</label>
                                <input
                                    id="b2b-wallet-transaction"
                                    type="text"
                                    name="transaction_uid"
                                    value="{{ old('transaction_uid', $form['transaction_uid']) }}"
                                    class="form-control"
                                    autocomplete="off"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="b2b-wallet-operator">Operator ID</label>
                                <input
                                    id="b2b-wallet-operator"
                                    type="number"
                                    min="1"
                                    name="operator_id"
                                    value="{{ old('operator_id', $form['operator_id']) }}"
                                    class="form-control"
                                >
                            </div>

                            <div class="form-group">
                                <label for="b2b-wallet-action">Action</label>
                                <select id="b2b-wallet-action" name="action" class="form-control" required>
                                    @foreach($actions as $action)
                                        <option value="{{ $action }}" {{ old('action', $form['action']) === $action ? 'selected' : '' }}>
                                            {{ $action }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="b2b-wallet-reason">Reason</label>
                                <textarea
                                    id="b2b-wallet-reason"
                                    name="reason"
                                    rows="4"
                                    class="form-control"
                                    required
                                >{{ old('reason', $form['reason']) }}</textarea>
                            </div>
                        </div>

                        <div class="box-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-check-circle"></i> Apply Manual Action
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'wallet.manual_action', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Step-Up
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Wallet Cases</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Operator</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Attempts</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td>
                                        <strong>{{ $transaction->transaction_uid ?: $transaction->transaction_id ?: $transaction->id }}</strong>
                                        @if($transaction->last_error)
                                            <br><small class="text-muted">{{ $transaction->last_error }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $transaction->operator_id ?: 'n/a' }}</td>
                                    <td>{{ $transaction->type ?: 'n/a' }}</td>
                                    <td>{{ $transaction->status ?: 'n/a' }}</td>
                                    <td>{{ $transaction->amount }} {{ $transaction->currency }}</td>
                                    <td>{{ (int) $transaction->attempts }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No wallet cases</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
