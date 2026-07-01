@extends('backend.layouts.app')

@section('page-title', 'B2B Operations')
@section('page-heading', 'B2B Operations')

@php
    $display = function ($value) {
        return $value === null ? 'n/a' : number_format((int) $value);
    };
@endphp

@section('content')
    <section class="content-header">
        <h1>B2B Operations</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-light-blue">
                    <div class="inner">
                        <h3>{{ $display($summary['operators_active']) }}</h3>
                        <p>Active Operators</p>
                    </div>
                    <div class="icon"><i class="fa fa-sitemap"></i></div>
                </div>
            </div>

            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ $display($summary['operator_circuits_open']) }}</h3>
                        <p>Open Circuits</p>
                    </div>
                    <div class="icon"><i class="fa fa-bolt"></i></div>
                </div>
            </div>

            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ $display($summary['sessions_active']) }}</h3>
                        <p>Active Sessions</p>
                    </div>
                    <div class="icon"><i class="fa fa-play-circle"></i></div>
                </div>
            </div>

            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ $display($summary['reconciliation_open']) }}</h3>
                        <p>Open Reconciliation</p>
                    </div>
                    <div class="icon"><i class="fa fa-warning"></i></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Wallet Status</h3>
                    </div>
                    <div class="box-body no-padding">
                        <table class="table table-striped">
                            <tbody>
                            @forelse($wallet_statuses as $status => $count)
                                <tr>
                                    <td>{{ $status ?: 'unknown' }}</td>
                                    <td class="text-right">{{ number_format($count) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">No wallet transactions</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Operator Status</h3>
                    </div>
                    <div class="box-body no-padding">
                        <table class="table table-striped">
                            <tbody>
                            @forelse($operator_statuses as $status => $count)
                                <tr>
                                    <td>{{ $status ?: 'unknown' }}</td>
                                    <td class="text-right">{{ number_format($count) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">No operators</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Settlement Status</h3>
                    </div>
                    <div class="box-body no-padding">
                        <table class="table table-striped">
                            <tbody>
                            @forelse($settlement_statuses as $status => $count)
                                <tr>
                                    <td>{{ $status ?: 'unknown' }}</td>
                                    <td class="text-right">{{ number_format($count) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">No settlements</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Recent Wallet Transactions</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Currency</th>
                                <th>Attempts</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recent_wallet_transactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->transaction_uid ?: 'n/a' }}</td>
                                    <td>{{ $transaction->type ?: 'n/a' }}</td>
                                    <td>{{ $transaction->status ?: 'n/a' }}</td>
                                    <td>{{ $transaction->currency ?: 'n/a' }}</td>
                                    <td>{{ (int) $transaction->attempts }}</td>
                                    <td>{{ $transaction->created_at ?: 'n/a' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No wallet transactions</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Reconciliation Queue</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>State</th>
                                <th>Priority</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recent_reconciliation_items as $item)
                                <tr>
                                    <td>{{ $item->transaction_uid ?: 'n/a' }}</td>
                                    <td>{{ $item->state ?: $item->status }}</td>
                                    <td>{{ $item->priority ?: 'normal' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">No reconciliation items</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Operational Links</h3>
                    </div>
                    <div class="box-body">
                        <p><a href="{{ route('backend.b2b.wallet_manual_actions.index') }}">Manual Wallet Actions</a></p>
                        <p><a href="{{ route('backend.b2b.settlements.index') }}">Settlement Workflow</a></p>
                        <p><a href="{{ route('backend.b2b.credentials.index') }}">Credential Lifecycle</a></p>
                        <p><a href="{{ route('backend.b2b.operators.index') }}">Operator Configuration</a></p>
                        <p><a href="/api/b2b/v1/health" target="_blank" rel="noopener">Health</a></p>
                        <p><a href="/api/b2b/v1/readiness" target="_blank" rel="noopener">Readiness</a></p>
                        <p><a href="/api/b2b/v1/metrics" target="_blank" rel="noopener">Metrics</a></p>
                        <p><code>docs/b2b/openapi.json</code></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
