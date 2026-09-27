@extends('layouts.app')

@section('title', 'Registo — GigRunner')

@section('content')
    <h1>Criar conta</h1>
    <p class="sub">Registo de teste. A mesma conta serve para a API e para esta web.</p>

    <div class="card">
        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmar password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Registar</button>
            </div>
        </form>
    </div>

    <p class="links">Já tens conta? <a href="{{ route('login') }}">Login</a></p>
@endsection
