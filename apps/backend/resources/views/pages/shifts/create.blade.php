@extends('layouts.workspace')
@section('title', 'Tambah shift')
@section('content')
@include('pages.shifts.form',['editing'=>false])
@endsection
