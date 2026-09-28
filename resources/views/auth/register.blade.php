@extends('layouts.app')

@section('title', __('ui.nav_register').' — GigRunner')

@section('content')
    <h1>{{ __('ui.register_title') }}</h1>
    <p class="sub">{{ __('ui.register_sub') }}</p>

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
                <label for="name">{{ __('ui.register_name') }}</label>
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
                <label for="password_confirmation">{{ __('ui.register_password_confirm') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <div class="actions">
                <button class="btn" type="submit">{{ __('ui.register_submit') }}</button>
            </div>
        </form>
    </div>

    <p class="links">{{ __('ui.register_has_account') }} <a href="{{ route('login') }}">{{ __('ui.nav_login') }}</a></p>
@endsection
