@extends('layouts.workspace')
@section('title', 'Tambah karyawan')
@section('content')
@include('pages.users.form', ['editing'=>false])
@endsection
