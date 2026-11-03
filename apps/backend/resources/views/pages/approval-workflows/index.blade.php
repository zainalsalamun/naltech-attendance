@extends('layouts.workspace')
@section('title','Alur persetujuan')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Alur persetujuan</p><h1>Alur persetujuan</h1><p class="muted">Tetapkan urutan peninjauan untuk pengajuan baru. Pengajuan yang sudah dikirim tetap mengikuti alurnya semula.</p></div></div>
@include('components.workspace-feedback')
@foreach(['permission'=>'Izin','overtime'=>'Lembur','reimbursement'=>'Reimbursement'] as $kind=>$label)
@php($policy=$policies->get($kind))
@php($steps=$policy ? json_decode($policy->steps,true) : [])
<section class="panel record-form-panel"><details data-persistent-details @if($kind==='permission') open @endif><summary class="panel-heading">{{ $label }} · {{ count($steps) ? count($steps).' tahap' : 'Satu keputusan' }}</summary><div class="record-form">
<p>Alur saat ini: {{ count($steps) ? implode(' → ',array_column($steps,'label')) : 'Satu keputusan oleh pengelola sesuai hak akses' }}.</p>
@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))
<form method="post" action="{{ route('approval-workflows.store') }}">@csrf
<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="expected_id" value="{{ $policy->id ?? 0 }}">
<p class="muted">Isi tahap berurutan, maksimal empat. Kosongkan nama tahap yang tidak digunakan. Pengganti digunakan saat peninjau utama nonaktif, belum ditetapkan, atau merupakan pemohon sendiri. Jika keduanya tidak memenuhi syarat, pengajuan menunggu peninjau yang memenuhi syarat kembali aktif.</p>
@for($i=0;$i<4;$i++)
@php($step=old('kind')===$kind ? old('draft.'.$i,$steps[$i] ?? []) : ($steps[$i] ?? []))
<fieldset class="workflow-step"><legend>Tahap {{ $i+1 }}</legend><div class="form-grid">
<div class="field"><label for="{{ $kind }}-label-{{ $i }}">Nama tahap</label><input id="{{ $kind }}-label-{{ $i }}" name="draft[{{ $i }}][label]" maxlength="80" value="{{ $step['label'] ?? '' }}"></div>
<div class="field"><label for="{{ $kind }}-type-{{ $i }}">Jenis peninjau</label><select id="{{ $kind }}-type-{{ $i }}" name="draft[{{ $i }}][type]"><option value="manager" @selected(($step['type'] ?? '')==='manager')>Atasan langsung saat pengajuan dikirim</option><option value="user" @selected(($step['type'] ?? '')==='user')>Pengelola tertentu</option></select></div>
@foreach(['reviewer_id'=>'Peninjau utama (untuk pengelola tertentu)','fallback_id'=>'Pengganti'] as $key=>$caption)
<div class="field"><label for="{{ $kind }}-{{ $key }}-{{ $i }}">{{ $caption }}</label><select id="{{ $kind }}-{{ $key }}-{{ $i }}" name="draft[{{ $i }}][{{ $key }}]"><option value="">Pilih pengelola</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected((int)($step[$key] ?? 0)===$reviewer->id)>{{ $reviewer->name }} · {{ $reviewer->role }}</option>@endforeach</select></div>
@endforeach
</div></fieldset>
@endfor
<div class="field"><label class="workflow-confirm"><input type="checkbox" name="confirm" value="1" required> Terapkan untuk pengajuan baru. Tanpa tahap, gunakan kembali satu keputusan sesuai hak akses.</label></div><div class="dialog-actions"><button class="primary">Simpan alur {{ strtolower($label) }}</button></div></form>
@else<p class="muted">Konfigurasi dikelola HR/admin.</p>@endif
</div></details></section>
@endforeach
<section class="panel"><div class="panel-heading"><h2>Riwayat konfigurasi</h2></div><div class="table-scroll" tabindex="0"><table><thead><tr><th>Waktu</th><th>Pengajuan</th><th>Pengelola</th><th>Urutan</th></tr></thead><tbody>@forelse($history as $entry)<tr><td>{{ $entry->created_at }} ({{ config('app.timezone') }})</td><td>{{ ['permission'=>'Izin','overtime'=>'Lembur','reimbursement'=>'Reimbursement'][$entry->kind] }}</td><td>{{ $entry->actor_name }}</td><td>{{ implode(' → ',array_column(json_decode($entry->steps,true),'label')) ?: 'Satu keputusan' }}</td></tr>@empty<tr><td colspan="4" class="empty-state">Belum ada konfigurasi khusus.</td></tr>@endforelse</tbody></table></div>@include('components.workspace-pagination',['paginator'=>$history,'unit'=>'perubahan'])</section>
@endsection
