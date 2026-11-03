@extends('layouts.auth')
@section('title','Halaman tidak ditemukan')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">404</p><h1>Halaman tidak ditemukan</h1><p class="muted">Tautan mungkin sudah berubah atau halaman yang diminta tidak tersedia.</p><div class="dialog-actions"><a class="secondary" href="{{ route('login') }}">Halaman masuk</a>@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="primary" type="submit">Keluar akun</button></form>@endauth</div></section>
@endsection
