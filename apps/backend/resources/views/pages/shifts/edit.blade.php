@extends('layouts.workspace')
@section('title', 'Edit shift')
@section('content')
@include('pages.shifts.form',['editing'=>true])
@endsection
