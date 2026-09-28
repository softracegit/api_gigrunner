@extends('layouts.app')

@section('title', __('ui.login_title').' — GigRunner')

@section('content')
    <h1>{{ __('ui.login_title') }}</h1>
    <p class="sub">{{ __('ui.login_sub') }}</p>

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
                {{ __('ui.login_remember') }}
            </label>
            <div class="actions">
                <button class="btn" type="submit">{{ __('ui.login_submit') }}</button>
            </div>
        </form>
    </div>

    <p class="links">{{ __('ui.login_no_account') }} <a href="{{ route('register') }}">{{ __('ui.login_register_link') }}</a></p>
@endsection
