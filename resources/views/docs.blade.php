@extends('layouts.app')

@section('title', app()->getLocale() === 'en' ? 'API Documentation — GigRunner' : 'Documentação API — GigRunner')
@section('body_class', 'shell-page')
@section('wrap_class', 'shell')

@section('content')
    @include(app()->getLocale() === 'en' ? 'docs.en' : 'docs.pt')
@endsection
