@extends('layouts.vue-app')

@section('title', 'Snippets')
@section('page', 'snippetsIndex')
@section('props')
@json(["snippets" => $snippets])
@endsection
