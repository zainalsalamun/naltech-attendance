@extends('layouts.auth')
@section('title','Aksi tidak tersedia')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">405</p><h1>Aksi tidak tersedia</h1><p class="muted">Halaman ini tidak mendukung aksi yang diminta.</p><div class="dialog-actions"><a class="secondary" href="{{ url('/') }}">Kembali</a></div></section>
@endsection
