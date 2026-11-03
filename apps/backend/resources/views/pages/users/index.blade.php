@extends('layouts.workspace')
@section('title', 'Karyawan')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Karyawan</p><h1>Karyawan</h1><p class="muted">Kelola identitas, status akun, dan jadwal kerja. Riwayat tetap tersimpan saat diarsipkan.</p></div><a class="secondary" href="{{ route('units.index') }}">Unit organisasi</a><a class="primary" href="{{ route('users.create') }}">Tambah karyawan</a></div>
@include('components.workspace-feedback')
<section class="panel"><div class="panel-heading"><div><h2>Daftar karyawan</h2><p class="muted">{{ $users->total() }} akun sesuai filter</p></div></div>
<form method="get" action="{{ route('users.index') }}" class="record-filters">
<div class="field"><label for="name">Nama atau nomor karyawan</label><input id="name" type="search" name="name" value="{{ $search }}" maxlength="100" placeholder="Cari nama atau nomor"></div>
<div class="field"><label for="status">Status kepegawaian</label><select id="status" name="status">@foreach(['active'=>'Aktif','inactive'=>'Nonaktif','archived'=>'Diarsipkan','all'=>'Semua status'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div>
<button type="submit" class="primary">Cari</button><a class="secondary" href="{{ route('users.index') }}">Reset pencarian</a></form>
<div class="table-scroll" tabindex="0" role="region" aria-label="Daftar karyawan — tabel dapat digeser horizontal"><table><caption class="sr-only">Daftar karyawan perusahaan aktif dan arsip</caption>
<thead><tr>@foreach(['Karyawan','Kontak','Jabatan / unit / atasan','Shift','Peran','Status / masa kerja','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($users as $employee)
<tr><td><div class="person"><span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($employee->name,0,2)) }}</span><div><strong>{{ $employee->name }}</strong><small>{{ $employee->employee_number ?: 'Nomor belum diatur' }}</small></div></div></td>
<td class="employee-contact">{{ $employee->email }}<br><span class="muted">{{ $employee->phone ?: 'Telepon belum diatur' }}</span></td>
<td>{{ $employee->position ?: 'Jabatan belum diatur' }}<br><span class="muted">Unit: {{ $employee->organizationUnit?->name ?: 'Belum dipetakan' }}{{ $employee->organizationUnit && !$employee->organizationUnit->is_active ? ' (nonaktif)' : '' }}<br>Atasan: {{ $employee->manager?->name ?: 'Belum diatur' }}{{ $employee->manager && !$employee->manager->isEmploymentActive() ? ' (nonaktif)' : '' }}<br>Departemen lama: {{ $employee->department ?: 'Belum diatur' }}</span></td>
<td>{{ $employee->shift?->name ?: 'Tanpa shift' }}@if($employee->shift)<br><span class="muted time">{{ substr($employee->shift->time_in,0,5) }}–{{ substr($employee->shift->time_out,0,5) }}</span>@endif</td>
<td><span class="badge">{{ ['staff'=>'Staf','admin'=>'Admin','super admin'=>'Super Admin','hrd'=>'HRD','supervisor'=>'Supervisor'][$employee->role] ?? ($employee->role ?: 'Belum diatur') }}</span></td>
<td><span class="badge">{{ $employee->archived_at ? 'Diarsipkan' : ($employee->employment_status==='active' ? 'Aktif' : 'Nonaktif') }}</span><br><small class="muted">Bergabung: {{ $employee->joined_on?->format('d/m/Y') ?: 'Belum diatur' }}<br>Keluar: {{ $employee->left_on?->format('d/m/Y') ?: 'Belum diatur' }}</small></td>
<td>@if(in_array(auth()->user()->role,['admin','super admin'],true) || $employee->role==='staff')
<div class="record-actions"><a class="secondary" href="{{ route('users.edit',$employee) }}" aria-label="Edit {{ $employee->name }}">Edit</a>
@if(!$employee->archived_at && $employee->id!==auth()->id())<button class="danger" type="button" data-attendance-dialog="archive-user-{{ $employee->id }}" aria-haspopup="dialog" aria-label="Arsipkan {{ $employee->name }}">Arsipkan</button>@endif</div>
@if(!$employee->archived_at && $employee->id!==auth()->id())
<dialog class="attendance-dialog archive-dialog" id="archive-user-{{ $employee->id }}" aria-labelledby="archive-title-{{ $employee->id }}" @if(old('archive_user_id') == $employee->id && session('conflict')) open @endif style="max-width:520px;width:92%;padding:20px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);box-shadow:var(--shadow-lg);">
<h2 id="archive-title-{{ $employee->id }}" style="margin:0 0 12px;font-size:18px;">Arsipkan karyawan?</h2>
<div class="dialog-content" style="font-size:13px;display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
<p style="margin:0"><strong>{{ $employee->name }}</strong> ({{ $employee->employee_number ?: 'Nomor belum diatur' }})</p>
<div style="background:var(--bg);padding:8px 10px;border-radius:4px;border:1px solid var(--line)">
Status awal: <strong>{{ $employee->employment_status === 'active' ? 'Aktif' : 'Nonaktif' }}</strong> → Status tujuan: <strong>Diarsipkan</strong>
</div>
<div style="background:var(--bg);padding:8px 10px;border-radius:4px;border:1px solid var(--line);border-left:3px solid var(--amber)">
<strong>Dampak akses:</strong> Akun dinonaktifkan dan akses login web serta API dicabut segera.
</div>
<p class="muted" style="margin:0;font-size:12px">
<strong>Pelestarian riwayat:</strong> Riwayat absensi, pengajuan cuti/lembur/reimbursement, dan log audit tetap tersimpan. Pengaktifan kembali tersedia melalui menu Edit.
</p>
</div>
<form id="archive-form-{{ $employee->id }}" method="post" action="{{ route('users.destroy',$employee) }}" class="archive-form-action">@csrf @method('DELETE')
<input type="hidden" name="archive_user_id" value="{{ $employee->id }}">
<input type="hidden" name="version" value="{{ $employee->version ?? 0 }}">
<div class="field"><label for="archive-reason-{{ $employee->id }}">Alasan arsip (wajib)</label><textarea id="archive-reason-{{ $employee->id }}" name="lifecycle_reason" maxlength="500" required rows="3" placeholder="Tuliskan alasan mengapa karyawan ini diarsipkan...">{{ old('archive_user_id') == $employee->id ? old('lifecycle_reason') : '' }}</textarea></div>
<div class="field"><label for="archive-left-{{ $employee->id }}">Tanggal keluar (opsional)</label><input type="date" id="archive-left-{{ $employee->id }}" name="left_on" value="{{ old('archive_user_id') == $employee->id ? old('left_on', $employee->left_on?->format('Y-m-d')) : $employee->left_on?->format('Y-m-d') }}"></div></form>
<div class="dialog-actions" style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px"><button type="button" class="secondary btn-close-archive" data-dialog-id="archive-user-{{ $employee->id }}">Batal</button><button class="danger btn-submit-archive" type="submit" form="archive-form-{{ $employee->id }}">Arsipkan akun</button></div></dialog>
@endif
@else<span class="muted">Dikelola admin</span>@endif</td></tr>
@empty<tr><td colspan="7" class="empty-state"><strong>Tidak ada karyawan</strong><p>{{ $search ? 'Coba nama lain atau reset pencarian.' : 'Pilih status lain atau tambahkan karyawan.' }}</p></td></tr>@endforelse
</tbody></table></div>@include('components.workspace-pagination',['paginator'=>$users,'unit'=>'akun'])</section>

<script>
(function() {
    var lastTrigger = null;

    document.querySelectorAll('[data-attendance-dialog]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var dialogId = btn.getAttribute('data-attendance-dialog');
            var dialog = document.getElementById(dialogId);
            if (dialog) {
                lastTrigger = btn;
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
            }
        });
    });

    document.querySelectorAll('.btn-close-archive').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var dialogId = btn.getAttribute('data-dialog-id');
            var dialog = document.getElementById(dialogId);
            if (dialog) {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
                if (lastTrigger && typeof lastTrigger.focus === 'function') {
                    lastTrigger.focus();
                }
            }
        });
    });

    document.querySelectorAll('.archive-dialog').forEach(function(dialog) {
        dialog.addEventListener('cancel', function() {
            setTimeout(function() {
                if (lastTrigger && typeof lastTrigger.focus === 'function') {
                    lastTrigger.focus();
                }
            }, 0);
        });
    });

    document.querySelectorAll('.archive-form-action').forEach(function(form) {
        var isSubmitting = false;
        form.addEventListener('submit', function(e) {
            var reason = (form.querySelector('textarea[name="lifecycle_reason"]')?.value || '').trim();
            if (!reason) {
                e.preventDefault();
                alert('Alasan arsip wajib diisi.');
                form.querySelector('textarea[name="lifecycle_reason"]')?.focus();
                return;
            }
            if (isSubmitting) {
                e.preventDefault();
                return;
            }
            isSubmitting = true;
            var submitBtn = document.querySelector('button[form="' + form.id + '"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses...';
            }
        });
    });
})();
</script>
@endsection
