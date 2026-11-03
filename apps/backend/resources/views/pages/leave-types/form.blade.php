@extends('layouts.workspace')
@section('title',$type->exists ? 'Kelola jenis cuti' : 'Tambah jenis cuti')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('leave-types.index') }}">Jenis cuti</a></p><h1>{{ $type->exists ? 'Kelola jenis cuti' : 'Tambah jenis cuti' }}</h1><p class="muted">Perubahan berlaku untuk pengajuan baru. Kebijakan pada pengajuan lama tetap tersimpan.</p></div></div>
@include('components.workspace-feedback')
<section class="panel record-form-panel"><div class="panel-heading"><h2>Aturan jenis cuti</h2></div><form class="record-form" method="post" action="{{ $type->exists ? route('leave-types.update',$type) : route('leave-types.store') }}">@csrf
@if($type->exists)
@method('PUT')
@endif
<input type="hidden" name="version" value="{{ $type->version ?? 0 }}">
<div class="form-grid">
@foreach(['code'=>['Kode unik','32'],'name'=>['Nama jenis cuti','120']] as $field=>[$label,$max])
<div class="field"><label for="{{ $field }}">{{ $label }} (wajib)</label><input id="{{ $field }}" name="{{ $field }}" maxlength="{{ $max }}" required value="{{ old($field,$type->$field) }}">@error($field)<span class="error">{{ $message }}</span>@enderror</div>
@endforeach
@foreach(['is_active'=>['Status','Aktif','Nonaktif'],'deducts_balance'=>['Aturan saldo','Mengurangi saldo','Tidak mengurangi saldo'],'requires_attachment'=>['Bukti pendukung','Wajib dilampirkan','Opsional']] as $field=>[$label,$yes,$no])
<div class="field"><label for="{{ $field }}">{{ $label }} (wajib)</label><select id="{{ $field }}" name="{{ $field }}" required><option value="">Pilih aturan</option><option value="1" @selected((string)old($field,$type->exists ? (int)$type->$field : '')==='1')>{{ $yes }}</option><option value="0" @selected((string)old($field,$type->exists ? (int)$type->$field : '')==='0')>{{ $no }}</option></select>@error($field)<span class="error">{{ $message }}</span>@enderror</div>
@endforeach
<div class="field"><label for="annual_quota_days">Kuota dasar per tahun (hari)</label><input id="annual_quota_days" name="annual_quota_days" type="number" min="0" max="366" step="1" value="{{ old('annual_quota_days',$type->annual_quota_days) }}"><span class="muted">Wajib bila mengurangi saldo. Boleh 0; maksimal 366 hari. Diabaikan bila tidak mengurangi saldo. Belum menjadi saldo karyawan.</span>@error('annual_quota_days')<span class="error">{{ $message }}</span>@enderror</div>
</div>
<p class="muted">Kode memakai huruf A–Z, angka, tanda hubung atau garis bawah. Nonaktif menghentikan pilihan baru tanpa menghapus riwayat. Pengajuan mengikuti alur persetujuan izin; saldo tidak dipotong pada tahap ini.</p>
<div class="field"><label for="requirements">Persyaratan / keterangan kebijakan</label><textarea id="requirements" name="requirements" maxlength="2000" rows="4">{{ old('requirements',$type->requirements) }}</textarea>@error('requirements')<span class="error">{{ $message }}</span>@enderror</div>
<div class="field"><label for="change_reason">Alasan pencatatan / perubahan (wajib)</label><textarea id="change_reason" name="change_reason" required maxlength="1000" rows="3">{{ old('change_reason') }}</textarea>@error('change_reason')<span class="error">{{ $message }}</span>@enderror</div>
<div class="dialog-actions"><a class="secondary" href="{{ route('leave-types.index') }}">Kembali</a><button class="primary">Simpan kebijakan</button></div></form></section>
@if($type->exists)
<section class="panel"><div class="panel-heading"><h2>Riwayat kebijakan terbaru</h2></div><div class="table-scroll" tabindex="0" role="region" aria-label="30 perubahan kebijakan terbaru"><table><thead><tr><th scope="col">Waktu</th><th scope="col">Pengelola</th><th scope="col">Aturan yang disimpan</th><th scope="col">Alasan</th></tr></thead><tbody>
@foreach($events as $event)
@php($after=json_decode($event->snapshot,true)['after'])
<tr><td>{{ $event->created_at }} ({{ config('app.timezone') }})</td><td>{{ $event->actor_name }}</td><td>{{ $after['code'] }} · {{ $after['name'] }}<p>{{ $after['is_active'] ? 'Aktif' : 'Nonaktif' }} · {{ $after['requires_attachment'] ? 'Bukti wajib' : 'Bukti opsional' }}</p><p>{{ $after['deducts_balance'] ? 'Kuota '.$after['annual_quota_days'].' hari / tahun; pemotongan belum aktif' : 'Tidak mengurangi saldo' }}</p><p>{{ $after['requirements'] }}</p></td><td class="permission-reason">{{ $event->reason }}</td></tr>
@endforeach
</tbody></table></div></section>
@endif
@endsection
