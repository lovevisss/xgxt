@extends('layouts.vue-app')

@section('title', '同步中心')
@section('page', 'syncTasks')
@section('props')
@json([
    'definitions' => $definitions,
    'tasks' => $tasks,
])
@endsection
