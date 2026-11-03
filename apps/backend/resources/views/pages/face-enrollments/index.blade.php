@extends('layouts.workspace')
@section('title', 'Pendaftaran wajah')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Pendaftaran wajah</p><h1>Pendaftaran wajah</h1><p class="muted">Tinjau identitas karyawan sebelum menyetujui foto referensi.</p></div></div>
@include('components.workspace-feedback')
<section class="panel">
    <div class="panel-heading"><div><h2>Foto referensi karyawan</h2><p class="muted">Persetujuan foto belum mengaktifkan absensi wajah. Verifikasi identitas dan liveness server masih dalam pengujian.</p></div></div>
    <form method="get" class="record-filters"><div class="field"><label for="status">Status</label><select id="status" name="status">@foreach(['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','revoked'=>'Dicabut','superseded'=>'Digantikan','expired'=>'Kedaluwarsa'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div><button class="primary">Tampilkan</button></form>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Daftar pendaftaran wajah"><table><thead><tr><th>Karyawan</th><th>Foto referensi</th><th>Pengajuan</th><th>Peninjauan</th></tr></thead><tbody>
    @forelse($enrollments as $enrollment)
    <tr><td><strong>{{ $enrollment->user?->name ?? 'Akun tidak tersedia' }}</strong><br><span class="muted">{{ $enrollment->user?->email }}</span></td>
    <td>@if(in_array($enrollment->status,['pending','approved']) && $enrollment->photo_path)<img src="{{ route('face-enrollments.photo',$enrollment) }}" alt="Foto referensi {{ $enrollment->user?->name }}" width="128" height="160" loading="lazy" style="object-fit:contain;border-radius:12px;background:var(--background,#f5f6f8)">@else<span class="muted">Foto tidak tersedia untuk status ini</span>@endif</td>
    <td>{{ $enrollment->created_at->format('d M Y H:i') }}<br><span class="badge">{{ ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','revoked'=>'Dicabut','superseded'=>'Digantikan','expired'=>'Kedaluwarsa'][$enrollment->status] }}</span></td>
    <td>@if($enrollment->reviewer)<p>Ditinjau oleh {{ $enrollment->reviewer->name }}</p>@endif @if($enrollment->review_reason)<p>{{ $enrollment->review_reason }}</p>@endif
    @if(in_array($enrollment->status,['pending','approved']) && $enrollment->user_id !== auth()->id())
        <form method="post" action="{{ route('face-enrollments.update',$enrollment) }}" class="field">@csrf @method('PATCH')
        @if($enrollment->status==='pending')
        <label class="face-confirmation" for="confirm-{{ $enrollment->id }}"><input id="confirm-{{ $enrollment->id }}" type="checkbox" name="identity_confirmed" value="1"> Saya telah memeriksa foto dan identitas karyawan ini.</label>
        @endif
        <label for="reason-{{ $enrollment->id }}">Alasan penolakan / pencabutan</label><textarea id="reason-{{ $enrollment->id }}" name="reason" maxlength="500" rows="2"></textarea><div class="record-actions">
        @if($enrollment->status==='pending')<button class="primary" name="decision" value="approved">Setujui</button><button class="secondary" name="decision" value="rejected">Tolak</button>@else<button class="danger" name="decision" value="revoked">Cabut persetujuan</button>@endif
        </div></form>
    @elseif($enrollment->user_id === auth()->id())<p class="muted">Pendaftaran sendiri harus ditinjau HR/admin lain.</p>@endif</td></tr>
    @empty<tr><td colspan="4" class="empty-state"><strong>Tidak ada pendaftaran pada status ini</strong><p>Karyawan dapat mengajukan foto dari menu Pendaftaran wajah di aplikasi mobile.</p></td></tr>@endforelse
    </tbody></table></div>
    @include('components.workspace-pagination',['paginator'=>$enrollments,'unit'=>'pendaftaran'])
</section>
@endsection
