@extends('layouts.auth')
@section('title','Masuk')
@section('main')
<section class="panel auth-panel"><p class="breadcrumb">WORKSPACE PERUSAHAAN</p><h1>Selamat datang kembali</h1><p class="muted">Masuk untuk mengakses kehadiran dan akun perusahaan Anda.</p>
@if($errors->any())<div class="notice error" id="login-error" role="alert">Belum dapat masuk. Periksa email dan kata sandi, atau tunggu sebentar sebelum mencoba lagi.</div>@endif
<form method="post" action="{{ route('login') }}" class="auth-form">@csrf<div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username" maxlength="255" value="{{ old('email') }}" @if($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif></div><div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password" @if($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif></div><button type="submit" class="primary">Masuk</button></form><p class="muted auth-help">Belum memiliki akun atau perlu bantuan masuk? Hubungi pengelola perusahaan Anda.</p></section>
@endsection
