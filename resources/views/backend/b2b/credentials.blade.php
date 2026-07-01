@extends('backend.layouts.app')

@section('page-title', 'B2B Credentials')
@section('page-heading', 'B2B Credentials')

@section('content')
    <section class="content-header">
        <h1>B2B Credentials</h1>
        @include('backend.partials.messages')
        @if($success)
            <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if($rotatedCredential)
            <div class="alert alert-warning">
                <p><strong>Operator:</strong> {{ $rotatedCredential['operator_uid'] }}</p>
                <p><strong>API Key:</strong> {{ $rotatedCredential['key_id'] }}</p>
                <p><strong>Secret:</strong> {{ $rotatedCredential['secret'] }}</p>
                <p><strong>Disabled existing keys:</strong> {{ $rotatedCredential['disabled_existing'] }}</p>
            </div>
        @endif
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Rotate API Key</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.credentials.rotate') }}">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-credential-operator">Operator UID</label>
                                <input id="b2b-credential-operator" type="text" name="operator_uid" value="{{ old('operator_uid') }}" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-credential-key">New Key ID</label>
                                <input id="b2b-credential-key" type="text" name="key_id" value="{{ old('key_id') }}" class="form-control" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="b2b-credential-rps">Max RPS</label>
                                <input id="b2b-credential-rps" type="number" min="1" name="max_rps" value="{{ old('max_rps') }}" class="form-control">
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="revoke_existing" value="1" {{ old('revoke_existing') ? 'checked' : '' }}>
                                    Disable existing active keys for this operator
                                </label>
                            </div>
                            <div class="form-group">
                                <label for="b2b-credential-rotate-reason">Reason</label>
                                <textarea id="b2b-credential-rotate-reason" name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-refresh"></i> Rotate Key
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'api_key.rotate', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Step-Up
                            </a>
                        </div>
                    </form>
                </div>

                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Revoke API Key</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.credentials.revoke') }}">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="b2b-revoke-operator">Operator UID</label>
                                <input id="b2b-revoke-operator" type="text" name="operator_uid" value="{{ old('operator_uid') }}" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-revoke-key">Key ID</label>
                                <input id="b2b-revoke-key" type="text" name="key_id" value="{{ old('key_id') }}" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="b2b-revoke-reason">Reason</label>
                                <textarea id="b2b-revoke-reason" name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-ban"></i> Revoke Key
                            </button>
                            <a href="{{ route('backend.b2b.step_up.show', ['action' => 'api_key.revoke', 'redirect_to' => request()->getRequestUri()]) }}" class="btn btn-default">
                                <i class="fa fa-shield"></i> Step-Up
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
                                <th>Operator UID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Currency</th>
                                <th>Max RPS</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($operators as $operator)
                                <tr>
                                    <td>{{ $operator->operator_uid }}</td>
                                    <td>{{ $operator->name }}</td>
                                    <td>{{ $operator->status }}</td>
                                    <td>{{ $operator->default_currency }}</td>
                                    <td>{{ $operator->max_rps }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No B2B operators</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">API Keys</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Operator ID</th>
                                <th>Key ID</th>
                                <th>Status</th>
                                <th>Max RPS</th>
                                <th>Last Used</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($apiKeys as $apiKey)
                                <tr>
                                    <td>{{ $apiKey->operator_id }}</td>
                                    <td>{{ $apiKey->key_id }}</td>
                                    <td>{{ $apiKey->status }}</td>
                                    <td>{{ $apiKey->max_rps ?: 'default' }}</td>
                                    <td>{{ $apiKey->last_used_at ?: 'never' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No B2B API keys</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
