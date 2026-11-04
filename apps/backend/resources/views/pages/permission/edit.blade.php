@extends('layouts.workspace')
@section('title', 'Persetujuan izin')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('permissions.index') }}">Pengajuan izin</a> / Persetujuan</p><h1>Persetujuan izin</h1><p class="muted">Perubahan akan memperbarui status izin dan rekap kehadiran terkait.</p></div></div>
@include('components.workspace-feedback')
<section class="panel record-form-panel"><div class="panel-heading"><h2>Tinjau pengajuan</h2></div><div class="record-form">@include('pages.permission.details')
</div></section>
@include('components.submission-approval',['submission'=>$permission,'kind'=>'permission'])
@endsection
