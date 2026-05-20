@extends('layouts.vue-app')

@section('title', '学生主页')
@section('page', 'studentProfile')
@section('props')
@php
    $pageProps = [
        "student" => $student,
        "families" => $families,
        "awards" => $awards,
        "punishments" => $punishments,
        "loans" => $loans,
        "canUpdateFamilies" => $canUpdateFamilies,
    ];
@endphp
@json($pageProps)
@endsection
