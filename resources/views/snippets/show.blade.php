@extends('layouts.vue-app')

@section('title', 'Snippet')
@section('page', 'snippetsShow')
@section('props')
@json(["snippet" => $snippet, "forks" => $snippet->forks])
@endsection
