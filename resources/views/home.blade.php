@extends('layouts.app')

@section('title', 'GigRunner API')
@section('wrap_class', 'wide')

@section('content')
    <h1>GigRunner API</h1>
    <p class="sub">Auth e licenças para a app. Escolhe por onde queres ir.</p>

    <div class="grid-2">
        <a class="card" href="{{ route('docs') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">Documentação</h2>
            <p class="sub" style="margin: 0;">Endpoints, fluxo e exemplos — fácil de partilhar com quem faz a app.</p>
        </a>
        <a class="card" href="{{ route('playground') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">Playground</h2>
            <p class="sub" style="margin: 0;">Registo, login, /me e /license no browser, sem Postman.</p>
        </a>
        <a class="card" href="{{ route('account') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">Conta (web)</h2>
            <p class="sub" style="margin: 0;">Simula a área do cliente: ver conta e activar/revogar licença de teste.</p>
        </a>
        <a class="card" href="{{ route('register') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">Registo / Login web</h2>
            <p class="sub" style="margin: 0;">Criar conta no site (sessão browser), separado do login da app.</p>
        </a>
    </div>
@endsection
