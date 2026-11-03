@extends('layouts.auth')
@section('title','Halaman belum dapat dimuat')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">500</p><h1>Halaman belum dapat dimuat</h1><p class="muted">Terjadi kendala saat memuat data. Coba kembali beberapa saat lagi.</p><div class="dialog-actions"><a class="secondary" href="{{ route('login') }}">Halaman masuk</a>@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="primary" type="submit">Keluar akun</button></form>@endauth</div></section>
@endsection
