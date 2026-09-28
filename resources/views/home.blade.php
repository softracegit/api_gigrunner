@extends('layouts.app')

@section('title', __('ui.home_title'))
@section('wrap_class', 'wide')

@section('content')
    <h1>{{ __('ui.home_title') }}</h1>
    <p class="sub">{{ __('ui.home_sub') }}</p>

    <div class="grid-2">
        <a class="card" href="{{ route('docs') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">{{ __('ui.home_docs_title') }}</h2>
            <p class="sub" style="margin: 0;">{{ __('ui.home_docs_sub') }}</p>
        </a>
        <a class="card" href="{{ route('playground') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">{{ __('ui.home_pg_title') }}</h2>
            <p class="sub" style="margin: 0;">{{ __('ui.home_pg_sub') }}</p>
        </a>
        <a class="card" href="{{ route('account') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">{{ __('ui.home_account_title') }}</h2>
            <p class="sub" style="margin: 0;">{{ __('ui.home_account_sub') }}</p>
        </a>
        <a class="card" href="{{ route('register') }}" style="text-decoration: none; color: inherit;">
            <h2 style="margin-top: 0; font-size: 1.1rem;">{{ __('ui.home_auth_title') }}</h2>
            <p class="sub" style="margin: 0;">{{ __('ui.home_auth_sub') }}</p>
        </a>
    </div>
@endsection
