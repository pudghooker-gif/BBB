@extends('backend.layouts.app')

@section('page-title', 'B2B Step-Up')
@section('page-heading', 'B2B Step-Up')

@section('content')
    <section class="content-header">
        <h1>B2B Step-Up</h1>
        @include('backend.partials.messages')
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $action }}</h3>
                    </div>
                    <form method="post" action="{{ route('backend.b2b.step_up.store', ['action' => $action]) }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $redirect_to }}">

                        <div class="box-body">
                            <dl>
                                <dt>Required Permission</dt>
                                <dd><code>{{ $required_permission }}</code></dd>
                                <dt>Confirmation Phrase</dt>
                                <dd><code>{{ $required_confirmation }}</code></dd>
                                <dt>Session TTL</dt>
                                <dd>{{ (int) $ttl_seconds }} seconds</dd>
                            </dl>

                            <div class="form-group">
                                <label for="b2b-step-up-confirm">Confirmation</label>
                                <input
                                    id="b2b-step-up-confirm"
                                    type="text"
                                    name="confirm"
                                    value="{{ old('confirm') }}"
                                    class="form-control"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    required
                                >
                            </div>
                        </div>

                        <div class="box-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-check-circle"></i> Confirm
                            </button>
                            <a href="{{ route('backend.b2b.dashboard') }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
