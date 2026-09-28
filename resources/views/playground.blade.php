@extends('layouts.app')

@section('title', app()->getLocale() === 'en' ? 'Playground — GigRunner API' : 'Playground — GigRunner API')
@section('body_class', 'shell-page')
@section('wrap_class', 'shell')

@section('content')
    @include(app()->getLocale() === 'en' ? 'playground.en' : 'playground.pt')
@endsection
