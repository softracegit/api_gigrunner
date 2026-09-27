@extends('layouts.app')

@section('title', 'Login — GigRunner')

@section('content')
    <h1>Login</h1>
    <p class="sub">Entra para ver a conta e gerar um token API de teste.</p>

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

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <label class="check">
                <input type="checkbox" name="remember" value="1">
                Manter sessão
            </label>
            <div class="actions">
                <button class="btn" type="submit">Entrar</button>
            </div>
        </form>
    </div>

    <p class="links">Ainda sem conta? <a href="{{ route('register') }}">Registar</a></p>
@endsection
