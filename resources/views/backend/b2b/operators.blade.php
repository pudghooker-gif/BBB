@extends('backend.layouts.app')

@section('page-title', 'B2B Operators')
@section('page-heading', 'B2B Operators')

@section('content')
    <section class="content-header">
        <h1>B2B Operators</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Update Operator</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.operators.update') }}">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-operator-uid">Operator UID</label>
                                <input id="b2b-operator-uid" type="text" name="operator_uid" value="{{ old('operator_uid') }}" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-name">Name</label>
                                <input id="b2b-operator-name" type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-shop">Shop ID</label>
                                <input id="b2b-operator-shop" type="number" min="0" name="shop_id" value="{{ old('shop_id') }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-base-url">Base URL</label>
                                <input id="b2b-operator-base-url" type="url" name="base_url" value="{{ old('base_url') }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-wallet-url">Wallet Callback URL</label>
                                <input id="b2b-operator-wallet-url" type="url" name="wallet_callback_url" value="{{ old('wallet_callback_url') }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-currency">Default Currency</label>
                                <input id="b2b-operator-currency" type="text" name="default_currency" value="{{ old('default_currency', 'USD') }}" class="form-control" maxlength="3" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-currencies">Allowed Currencies</label>
                                <input id="b2b-operator-currencies" type="text" name="allowed_currencies" value="{{ old('allowed_currencies') }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-countries">Allowed Countries</label>
                                <input id="b2b-operator-countries" type="text" name="allowed_countries" value="{{ old('allowed_countries') }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-ips">IP Whitelist</label>
                                <textarea id="b2b-operator-ips" name="ip_whitelist" rows="3" class="form-control">{{ old('ip_whitelist') }}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="b2b-operator-rps">Max RPS</label>
                                        <input id="b2b-operator-rps" type="number" min="1" name="max_rps" value="{{ old('max_rps', 50) }}" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="b2b-operator-wallet-timeout">Wallet Timeout MS</label>
                                        <input id="b2b-operator-wallet-timeout" type="number" min="100" name="wallet_timeout_ms" value="{{ old('wallet_timeout_ms', 5000) }}" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="b2b-operator-connect-timeout">Connect Timeout MS</label>
                                        <input id="b2b-operator-connect-timeout" type="number" min="100" name="connect_timeout_ms" value="{{ old('connect_timeout_ms', 1500) }}" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="b2b-operator-threshold">Circuit Threshold</label>
                                        <input id="b2b-operator-threshold" type="number" min="1" name="circuit_breaker_threshold" value="{{ old('circuit_breaker_threshold', 5) }}" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-cooldown">Circuit Cooldown Seconds</label>
                                <input id="b2b-operator-cooldown" type="number" min="1" name="circuit_breaker_cooldown_seconds" value="{{ old('circuit_breaker_cooldown_seconds', 30) }}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-operator-reason">Reason</label>
                                <textarea id="b2b-operator-reason" name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Update Operator
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'operator.update', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Step-Up
                            </a>
                        </div>
                    </form>
                </div>

                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Status Actions</h3>
                    </div>
                    <form method="post">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-status-operator">Operator UID</label>
                                <input id="b2b-status-operator" type="text" name="operator_uid" value="{{ old('operator_uid') }}" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-status-reason">Reason</label>
                                <textarea id="b2b-status-reason" name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" formaction="{{ route('backend.b2b.operators.suspend') }}" class="btn btn-danger">
                                <i class="fa fa-pause"></i> Suspend
                            </button>
                            <button type="submit" formaction="{{ route('backend.b2b.operators.resume') }}" class="btn btn-success">
                                <i class="fa fa-play"></i> Resume
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'operator.suspend', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Suspend Step-Up
                            </a>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'operator.resume', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Resume Step-Up
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Operators</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Operator</th>
                                <th>Status</th>
                                <th>Limits</th>
                                <th>Currency</th>
                                <th>Network</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($operators as $operator)
                                <tr>
                                    <td>
                                        <strong>{{ $operator->operator_uid }}</strong>
                                        <br><small class="text-muted">{{ $operator->name }}</small>
                                    </td>
                                    <td>{{ $operator->status }}</td>
                                    <td>
                                        RPS {{ $operator->max_rps }}
                                        <br><small class="text-muted">{{ $operator->wallet_timeout_ms }} / {{ $operator->connect_timeout_ms }} ms</small>
                                    </td>
                                    <td>
                                        {{ $operator->default_currency }}
                                        <br><small class="text-muted">{{ $operator->allowed_currencies ?: 'all configured' }}</small>
                                    </td>
                                    <td>
                                        <small>{{ $operator->base_url ?: 'n/a' }}</small>
                                        <br><small class="text-muted">{{ $operator->ip_whitelist ?: 'no ip whitelist' }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No B2B operators</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
