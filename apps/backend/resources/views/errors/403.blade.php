@extends('layouts.auth')
@section('title','Akses belum tersedia')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">403</p><h1>Akses belum tersedia</h1><p class="muted">Akun Anda belum memiliki akses ke halaman ini atau belum terhubung ke perusahaan. Hubungi pengelola perusahaan.</p><div class="dialog-actions"><a class="secondary" href="{{ route('login') }}">Halaman masuk</a>@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="primary" type="submit">Keluar akun</button></form>@endauth</div></section>
@endsection
