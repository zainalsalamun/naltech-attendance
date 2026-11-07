@extends('layouts.workspace')
@section('title','Unit organisasi')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Unit organisasi</p><h1>Unit organisasi</h1><p class="muted">Kelola unit perusahaan. Menonaktifkan unit mempertahankan penempatan karyawan.</p></div>@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))<a class="primary" href="{{ route('units.create') }}">Tambah unit</a>@endif</div>
@include('components.workspace-feedback')
<section class="panel"><div class="panel-heading"><h2>Daftar unit</h2></div>
<form class="record-filters" method="get"><div class="field"><label for="q">Nama atau kode unit</label><input id="q" name="q" value="{{ $search }}" maxlength="100" type="search"></div><div class="field"><label for="status">Status unit</label><select id="status" name="status">@foreach(['active'=>'Aktif','inactive'=>'Nonaktif','all'=>'Semua status'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div><button class="primary">Cari</button><a class="secondary" href="{{ route('units.index') }}">Reset</a></form>
<div class="table-scroll" tabindex="0" role="region" aria-label="Daftar unit, dapat digeser horizontal"><table><thead><tr><th scope="col">Kode</th><th scope="col">Nama unit</th><th scope="col">Status</th><th scope="col">Karyawan terhubung</th><th scope="col">Aksi</th></tr></thead><tbody>
@forelse($units as $unit)<tr><td>{{ $unit->code }}</td><td>{{ $unit->name }}</td><td>{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>{{ $unit->users_count }}</td><td>@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))<a class="secondary" href="{{ route('units.edit',$unit) }}" aria-label="Edit {{ $unit->name }}">Edit</a>@else<span class="muted">Dikelola HR/admin</span>@endif</td></tr>
@empty<tr><td colspan="5" class="empty-state"><strong>Tidak ada unit sesuai filter</strong><p>Tambahkan unit atau ubah pencarian.</p></td></tr>@endforelse
</tbody></table></div>@include('components.workspace-pagination',['paginator'=>$units,'unit'=>'unit'])</section>
@endsection
