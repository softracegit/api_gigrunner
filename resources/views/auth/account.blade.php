@extends('layouts.app')

@section('title', 'Conta — GigRunner')
@section('wrap_class', 'wide')

@section('content')
    <h1>A tua conta</h1>
    <p class="sub">Área web do cliente: ver dados e simular compra/activação de licença.</p>

    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="meta">
            <div class="meta-row"><span>Nome</span><div>{{ $user->name }}</div></div>
            <div class="meta-row"><span>Email</span><div>{{ $user->email }}</div></div>
            <div class="meta-row"><span>UUID</span><div><code>{{ $user->uuid }}</code></div></div>
            <div class="meta-row"><span>ID</span><div>{{ $user->id }}</div></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top: 0; font-size: 1.1rem;">Licença</h2>
        <p class="sub" style="margin-bottom: 16px;">No futuro isto activa-se com pagamento. Agora podes simular.</p>

        @php
            $valid = $license?->isValid() ?? false;
            $payload = $license?->toApiArray() ?? \App\Models\License::inactivePayload();
        @endphp

        <div class="meta">
            <div class="meta-row">
                <span>Válida</span>
                <div style="color: {{ $valid ? 'var(--ok)' : 'var(--danger)' }}; font-weight: 650;">
                    {{ $valid ? 'sim' : 'não' }}
                </div>
            </div>
            <div class="meta-row"><span>Status</span><div><code>{{ $payload['status'] }}</code></div></div>
            <div class="meta-row"><span>Plano</span><div>{{ $payload['plan'] ?? '—' }}</div></div>
            <div class="meta-row"><span>Expira</span><div>{{ $payload['expires_at'] ?? '—' }}</div></div>
        </div>

        <div class="actions" style="margin-top: 16px;">
            @unless ($valid)
                <form method="POST" action="{{ route('account.activate-test-license') }}">
                    @csrf
                    <button class="btn" type="submit">Activar licença de teste (30 dias)</button>
                </form>
            @else
                <form method="POST" action="{{ route('account.revoke-license') }}">
                    @csrf
                    <button class="btn secondary" type="submit">Revogar licença (teste)</button>
                </form>
            @endunless
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="actions">
        @csrf
        <button class="btn danger" type="submit">Logout</button>
    </form>

    <p class="hint">
        Para testar a API como a app: usa o <a href="{{ route('playground') }}" style="color: var(--accent);">Playground</a>
        (login → GET /license).
    </p>
@endsection
