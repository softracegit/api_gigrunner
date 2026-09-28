@extends('layouts.app')

@section('title', __('ui.nav_account').' — GigRunner')
@section('wrap_class', 'wide')

@section('content')
    <h1>{{ __('ui.account_title') }}</h1>
    <p class="sub">{{ __('ui.account_sub') }}</p>

    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="meta">
            <div class="meta-row"><span>{{ __('ui.account_name') }}</span><div>{{ $user->name }}</div></div>
            <div class="meta-row"><span>Email</span><div>{{ $user->email }}</div></div>
            <div class="meta-row"><span>UUID</span><div><code>{{ $user->uuid }}</code></div></div>
            <div class="meta-row"><span>ID</span><div>{{ $user->id }}</div></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top: 0; font-size: 1.1rem;">{{ __('ui.account_license') }}</h2>
        <p class="sub" style="margin-bottom: 16px;">{{ __('ui.account_license_sub') }}</p>

        @php
            $valid = $license?->isValid() ?? false;
            $payload = $license?->toApiArray() ?? \App\Models\License::inactivePayload();
        @endphp

        <div class="meta">
            <div class="meta-row">
                <span>{{ __('ui.account_valid') }}</span>
                <div style="color: {{ $valid ? 'var(--ok)' : 'var(--danger)' }}; font-weight: 650;">
                    {{ $valid ? __('ui.account_yes') : __('ui.account_no') }}
                </div>
            </div>
            <div class="meta-row"><span>{{ __('ui.account_status') }}</span><div><code>{{ $payload['status'] }}</code></div></div>
            <div class="meta-row"><span>{{ __('ui.account_plan') }}</span><div>{{ $payload['plan'] ?? '—' }}</div></div>
            <div class="meta-row"><span>{{ __('ui.account_expires') }}</span><div>{{ $payload['expires_at'] ?? '—' }}</div></div>
        </div>

        <div class="actions" style="margin-top: 16px;">
            @unless ($valid)
                <form method="POST" action="{{ route('account.activate-test-license') }}">
                    @csrf
                    <button class="btn" type="submit">{{ __('ui.account_activate') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('account.revoke-license') }}">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('ui.account_revoke') }}</button>
                </form>
            @endunless
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="actions">
        @csrf
        <button class="btn danger" type="submit">{{ __('ui.account_logout') }}</button>
    </form>

    <p class="hint">
        {!! __('ui.account_hint', [
            'link' => '<a href="'.route('playground').'" style="color: var(--accent);">'.e(__('ui.account_hint_link')).'</a>',
        ]) !!}
    </p>
@endsection
