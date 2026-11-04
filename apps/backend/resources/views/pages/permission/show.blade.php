@extends('layouts.workspace')
@section('title', 'Detail Pengajuan Cuti / Izin')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('permissions.index') }}">Cuti dan izin</a> / Detail</p>
        <h1>Detail Pengajuan Cuti / Izin</h1>
        <p class="muted">Tinjau rincian hari kerja, saldo cuti, lampiran bukti, dan riwayat persetujuan.</p>
    </div>
    <div>
        <a class="secondary" href="{{ route('permissions.index') }}"><i class="ion-ios-arrow-back" aria-hidden="true"></i> Kembali ke daftar</a>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel record-form-panel" style="margin-bottom:24px">
    <div class="panel-heading">
        <h2>Informasi Pengajuan</h2>
    </div>
    <div class="record-form">
        @include('pages.permission.details')
    </div>
</section>

@include('components.submission-approval', ['submission' => $permission, 'kind' => 'permission'])
@endsection
