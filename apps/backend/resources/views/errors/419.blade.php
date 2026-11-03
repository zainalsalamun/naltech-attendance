@extends('layouts.auth')
@section('title','Sesi kedaluwarsa')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">419</p><h1>Sesi kedaluwarsa</h1><p class="muted">Muat ulang halaman dan masuk kembali sebelum mengirim formulir.</p><div class="dialog-actions"><a class="secondary" href="{{ route('login') }}">Halaman masuk</a>@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="primary" type="submit">Keluar akun</button></form>@endauth</div></section>
@endsection
