@extends('layouts.vue-app')

@section('title', 'New Snippet')
@section('page', 'snippetsCreate')
@section('props')
@json(["snippet" => $snippet, "csrf" => csrf_token()])
@endsection
