@extends('layouts.workspace')
@section('title','Jenis cuti')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Jenis cuti</p><h1>Jenis dan kebijakan cuti</h1><p class="muted">Atur jenis cuti perusahaan, persyaratan bukti dan kuota kebijakan.</p></div>
@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))
<a class="primary" href="{{ route('leave-types.create') }}">Tambah jenis cuti</a>
@endif
</div>
@include('components.workspace-feedback')
<section class="panel"><div class="panel-heading"><div><h2>Kebijakan perusahaan</h2><p class="muted">Kuota adalah aturan dasar; saldo karyawan dan pemotongan otomatis belum tersedia. Pengajuan menggunakan <a href="{{ route('approval-workflows.index') }}">alur persetujuan izin</a>.</p></div></div>
<form class="record-filters" method="get"><div class="field"><label for="q">Kode atau nama jenis</label><input id="q" name="q" type="search" maxlength="100" value="{{ $search }}"></div><div class="field"><label for="status">Status</label><select id="status" name="status">@foreach(['all'=>'Semua','active'=>'Aktif','inactive'=>'Nonaktif'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div><button class="primary">Terapkan</button><a class="secondary" href="{{ route('leave-types.index') }}">Reset</a></form>
<div class="table-scroll" tabindex="0" role="region" aria-label="Daftar jenis cuti, dapat digeser horizontal"><table><thead><tr><th scope="col">Kode / jenis</th><th scope="col">Status</th><th scope="col">Aturan saldo</th><th scope="col">Kuota kebijakan</th><th scope="col">Lampiran</th><th scope="col">Aksi</th></tr></thead><tbody>
@forelse($types as $type)
<tr><td><strong>{{ $type->code }}</strong><p>{{ $type->name }}</p></td><td>{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>{{ $type->deducts_balance ? 'Mengurangi saldo (pemotongan belum aktif)' : 'Tidak mengurangi saldo' }}</td><td>{{ $type->annual_quota_days===null ? 'Tidak berlaku' : $type->annual_quota_days.' hari / tahun' }}</td><td>{{ $type->requires_attachment ? 'Wajib' : 'Opsional' }}</td><td>
@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))
<a class="secondary" href="{{ route('leave-types.edit',$type) }}">Kelola</a>
@else
<span class="muted">Dikelola HR/admin</span>
@endif
</td></tr>
@empty
<tr><td colspan="6" class="empty-state"><strong>Belum ada jenis cuti sesuai filter</strong><p>HR/admin dapat menambahkan kebijakan perusahaan tanpa mengubah pengajuan lama.</p></td></tr>
@endforelse
</tbody></table></div>@include('components.workspace-pagination',['paginator'=>$types,'unit'=>'jenis'])</section>
@endsection
