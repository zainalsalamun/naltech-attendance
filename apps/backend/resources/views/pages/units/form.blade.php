@extends('layouts.workspace')
@section('title',$unit->exists ? 'Edit unit' : 'Tambah unit')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('units.index') }}">Unit organisasi</a></p><h1>{{ $unit->exists ? 'Edit unit' : 'Tambah unit' }}</h1><p class="muted">Kode unik dalam perusahaan; unit nonaktif tidak menerima penempatan baru.</p></div></div>
@include('components.workspace-feedback')
<section class="panel record-form-panel"><div class="panel-heading"><h2>Informasi unit</h2></div><form class="record-form" method="post" action="{{ $unit->exists ? route('units.update',$unit) : route('units.store') }}">@csrf @if($unit->exists) @method('PUT') @endif
<div class="form-grid">@foreach(['code'=>'Kode unit','name'=>'Nama unit'] as $field=>$label)<div class="field"><label for="{{ $field }}">{{ $label }} (wajib)</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$unit->$field) }}" maxlength="{{ $field==='code' ? 50 : 150 }}" required @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@error($field)<span class="error" id="{{ $field }}-error">{{ $message }}</span>@enderror</div>@endforeach
<div class="field"><label for="is_active">Status unit</label><select id="is_active" name="is_active" required><option value="1" @selected((string)old('is_active',$unit->exists ? (int)$unit->is_active : 1)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',(int)$unit->is_active)==='0' && ($unit->exists || old('is_active')==='0'))>Nonaktif</option></select><span class="muted">Menonaktifkan unit tidak menghapus karyawan atau hubungan yang sudah ada.</span>@error('is_active')<span class="error">{{ $message }}</span>@enderror</div></div>
<div class="dialog-actions"><a class="secondary" href="{{ route('units.index') }}">Batal</a><button class="primary">Simpan unit</button></div></form></section>
@endsection
