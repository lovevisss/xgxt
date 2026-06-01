@extends('layouts.vue-app')

@section('title', '宿舍详情')
@section('page', 'studentDormitory')
@section('props')
@php
    $pageProps = [
        'ssh' => $ssh,
        'residents' => $residents,
        'dormitorySummary' => $dormitorySummary,
    ];
@endphp
@json($pageProps)
@endsection

