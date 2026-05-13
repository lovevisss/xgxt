@extends('layouts.vue-app')

@section('title', '学生主页')
@section('page', 'studentProfile')
@section('props')
@json(["student" => $student, "families" => $families])
@endsection
