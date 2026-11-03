@extends('layouts.workspace')
@section('title','Jadwal efektif')
@section('content')
<div class="page-heading"><div><p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Jadwal efektif</p><h1>Jadwal kerja efektif</h1><p class="muted">Penempatan shift berdasarkan tanggal mulai, dengan salinan jam dan zona waktu. Perubahan master shift tidak mengganti penempatan ini.</p></div></div>
@include('components.workspace-feedback')
@if(session('conflict'))
<div class="notice amber" role="alert" id="shift-conflict-notice" style="margin-bottom:18px">
    <strong>Konflik Penugasan Jadwal:</strong> {{ session('conflict') }}
</div>
@endif

<section class="panel record-form-panel"><form class="record-form" method="get"><div class="field"><label for="employee">Karyawan</label><select id="employee" name="user_id">@foreach($users as $employee)<option value="{{ $employee->id }}" @selected($selected?->id===$employee->id)>{{ $employee->name }}</option>@endforeach</select></div><button class="secondary">Lihat jadwal</button></form></section>
@if($selected)
<section class="panel record-form-panel"><div class="panel-heading"><h2>{{ $selected->name }}</h2></div><div class="record-form"><p>Jadwal hari ini: <span id="current-schedule-name">{{ $schedule['scheduled_name'] ?? 'Belum ada shift' }}</span> · <span id="current-schedule-times">{{ $schedule['scheduled_in'] ?? '—' }}–{{ $schedule['scheduled_out'] ?? '—' }}</span> · <span id="current-schedule-tz">{{ $schedule['attendance_timezone'] ?? $today }}</span>.</p><p class="muted">{{ $schedule['schedule_source']==='legacy' ? 'Masih memakai penempatan lama tanpa riwayat efektif.' : 'Mengikuti riwayat tanggal efektif.' }} Checkout sesi baru dibatasi maksimal 24 jam setelah check-in. Sesi yang lewat batas perlu koreksi HR.</p>
@if(in_array(auth()->user()->role,['admin','super admin','hrd'],true))
<form id="effective-shift-form" method="post" action="{{ route('effective-shifts.store') }}">@csrf<input type="hidden" name="user_id" value="{{ $selected->id }}"><input type="hidden" name="expected_id" value="{{ $expected }}"><div class="form-grid"><div class="field"><label for="shift">Shift</label><select id="shift" name="shift_id"><option value="" data-name="Tanpa shift" data-in="—" data-out="—" data-cross="0">Tanpa shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}" data-name="{{ $shift->name }}" data-in="{{ $shift->time_in }}" data-out="{{ $shift->time_out }}" data-cross="{{ $shift->time_out < $shift->time_in ? '1' : '0' }}" @selected((string)old('shift_id')===(string)$shift->id)>{{ $shift->name }} · {{ $shift->time_in }}–{{ $shift->time_out }}</option>@endforeach</select></div><div class="field"><label for="effective">Mulai berlaku (wajib)</label><input id="effective" name="effective_from" type="date" min="{{ $today }}" value="{{ old('effective_from',$today) }}" required></div></div><div class="field"><label for="reason">Alasan perubahan (wajib)</label><textarea id="reason" name="reason" maxlength="1000" rows="3" required placeholder="Contoh: Penyesuaian rotasi operasional divisi">{{ old('reason') }}</textarea></div><p class="muted">Tidak bisa mengubah tanggal lampau atau hari ini bila sudah ada absensi. Penyimpanan ulang tanggal yang sama menambah revisi; revisi terakhir berlaku. Jadwal berikutnya tetap mengikuti tanggal efektifnya. Tanpa shift tidak berarti hari libur.</p><div class="dialog-actions"><button class="primary" type="button" id="btn-open-preview">Tinjau & Simpan Jadwal</button></div></form>

{{-- Modal Pratinjau Penugasan Jadwal Efektif --}}
<dialog id="shift-assignment-preview-dialog" class="attendance-dialog" aria-labelledby="shift-preview-dialog-title" style="max-width:540px;width:92%;padding:20px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);box-shadow:var(--shadow-lg);">
    <div class="dialog-heading" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
        <h2 id="shift-preview-dialog-title" style="margin:0;font-size:18px;">Tinjau Penugasan Jadwal Efektif</h2>
        <button type="button" id="shift-preview-close" class="secondary" style="padding:4px 8px;font-size:12px;">✕ Tutup</button>
    </div>
    <div class="dialog-content" style="font-size:13px;display:flex;flex-direction:column;gap:12px">
        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line)">
            <div><strong>Karyawan:</strong> {{ $selected->name }}</div>
            <div><strong>Zona Waktu:</strong> {{ $schedule['attendance_timezone'] ?? $today }}</div>
            <div><strong>Jadwal Saat Ini:</strong> {{ $schedule['scheduled_name'] ?? 'Belum ada shift' }} ({{ $schedule['schedule_source']==='legacy' ? 'Penempatan lama' : 'Jadwal efektif' }})</div>
        </div>

        <table style="width:100%;font-size:13px;border-collapse:collapse;margin-top:4px">
            <thead>
                <tr style="border-bottom:1px solid var(--line);text-align:left">
                    <th style="padding:6px 4px">Field</th>
                    <th style="padding:6px 4px">Sebelum (Jadwal Berlaku pada Tanggal Target)</th>
                    <th style="padding:6px 4px">Sesudah (Penugasan Baru)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Nama Shift</td>
                    <td style="padding:6px 4px"><code id="preview-before-name">{{ $schedule['scheduled_name'] ?? 'Tanpa shift' }}</code></td>
                    <td style="padding:6px 4px"><strong><code id="preview-shift-name">—</code></strong></td>
                </tr>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Jam Kerja</td>
                    <td style="padding:6px 4px"><code class="time" id="preview-before-times">{{ $schedule['scheduled_in'] ?? '—' }}–{{ $schedule['scheduled_out'] ?? '—' }}</code></td>
                    <td style="padding:6px 4px"><strong><code class="time" id="preview-shift-times">—</code></strong></td>
                </tr>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Mulai Berlaku</td>
                    <td style="padding:6px 4px"><small class="muted" id="preview-before-effective">—</small></td>
                    <td style="padding:6px 4px"><strong><span id="preview-effective-from">—</span></strong></td>
                </tr>
            </tbody>
        </table>
        <p class="muted" style="margin:4px 0 0;font-size:12px">
            Pembanding penugasan baru adalah jadwal yang berlaku pada tanggal target sebelum perubahan, termasuk jika sudah ada penugasan pada tanggal tersebut.
        </p>

        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line);border-left:3px solid var(--accent)">
            <div style="font-weight:600;margin-bottom:4px">Dampak pada Sesi & Tanggal Berlaku</div>
            <div id="preview-session-impact" class="muted" style="font-size:12px;line-height:1.45"></div>
        </div>

        <div>
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Alasan Perubahan</div>
            <div id="preview-shift-reason" style="background:var(--bg);padding:8px 10px;border-radius:4px;border:1px solid var(--line);white-space:pre-wrap;color:var(--ink);font-size:13px"></div>
        </div>

        <p class="muted" style="margin:4px 0 0;font-size:12px">
            Penugasan baru disimpan sebagai revisi jadwal secara atomik. Riwayat penempatan dan catatan absensi lampau tetap dipertahankan.
        </p>
    </div>
    <div class="dialog-actions" style="margin-top:18px;display:flex;justify-content:flex-end;gap:8px">
        <button type="button" id="shift-preview-back" class="secondary">Kembali mengedit</button>
        <button type="button" id="shift-preview-confirm" class="primary">Konfirmasi simpan jadwal</button>
    </div>
</dialog>

<script>
(function() {
    var form = document.getElementById('effective-shift-form');
    var dialog = document.getElementById('shift-assignment-preview-dialog');
    var btnOpen = document.getElementById('btn-open-preview');
    var btnClose = document.getElementById('shift-preview-close');
    var btnBack = document.getElementById('shift-preview-back');
    var btnConfirm = document.getElementById('shift-preview-confirm');

    var shiftSelect = document.getElementById('shift');
    var effectiveInput = document.getElementById('effective');
    var reasonInput = document.getElementById('reason');

    var previewShiftName = document.getElementById('preview-shift-name');
    var previewShiftTimes = document.getElementById('preview-shift-times');
    var previewEffectiveFrom = document.getElementById('preview-effective-from');
    var previewSessionImpact = document.getElementById('preview-session-impact');
    var previewShiftReason = document.getElementById('preview-shift-reason');
    var previewBeforeName = document.getElementById('preview-before-name');
    var previewBeforeTimes = document.getElementById('preview-before-times');
    var previewBeforeEffective = document.getElementById('preview-before-effective');

    var todayStr = @json($today);
    var userAssignments = @json($assignmentsData ?? []);
    var legacyShift = @json($legacyShiftData ?? null);

    if (!form || !dialog || !btnOpen) return;

    btnOpen.addEventListener('click', function() {
        if (!form.reportValidity()) {
            return;
        }

        var reason = reasonInput.value.trim();
        if (!reason) {
            reasonInput.setCustomValidity('Alasan perubahan jadwal wajib diisi.');
            form.reportValidity();
            return;
        } else {
            reasonInput.setCustomValidity('');
        }

        var selectedOpt = shiftSelect.options[shiftSelect.selectedIndex] || {};
        var shiftName = selectedOpt.getAttribute('data-name') || 'Tanpa shift';
        var timeIn = selectedOpt.getAttribute('data-in') || '—';
        var timeOut = selectedOpt.getAttribute('data-out') || '—';
        var isCross = selectedOpt.getAttribute('data-cross') === '1';
        var effectiveDate = effectiveInput.value;

        // Cari jadwal yang berlaku pada effectiveDate sebelum penugasan baru ini
        var baseline = userAssignments.find(function(a) { return a.effective_from <= effectiveDate; });
        var beforeName = 'Tanpa shift';
        var beforeTimes = '—';
        var beforeEffective = 'Penempatan awal';

        if (baseline) {
            beforeName = baseline.shift_name || 'Tanpa shift';
            beforeTimes = (baseline.time_in && baseline.time_out) ? (baseline.time_in + '–' + baseline.time_out) : '—';
            beforeEffective = 'Mulai ' + baseline.effective_from;
        } else if (userAssignments.length === 0 && legacyShift) {
            beforeName = legacyShift.name || 'Tanpa shift';
            beforeTimes = (legacyShift.time_in && legacyShift.time_out) ? (legacyShift.time_in + '–' + legacyShift.time_out) : '—';
            beforeEffective = 'Master shift perusahaan';
        }

        if (previewBeforeName) previewBeforeName.textContent = beforeName;
        if (previewBeforeTimes) previewBeforeTimes.textContent = beforeTimes;
        if (previewBeforeEffective) previewBeforeEffective.textContent = beforeEffective;

        previewShiftName.textContent = shiftName;
        previewShiftTimes.textContent = (timeIn !== '—' && timeOut !== '—') ? (timeIn + '–' + timeOut) : '—';
        previewEffectiveFrom.textContent = effectiveDate;
        previewShiftReason.textContent = reason;

        var impactText = '';
        if (effectiveDate === todayStr) {
            impactText = 'Jadwal baru berlaku mulai hari ini (' + todayStr + '). Catatan kehadiran hari ini yang belum check-in akan menggunakan jadwal ini. Jika karyawan sudah memiliki catatan absensi pada hari ini, penyimpanan akan ditolak server untuk menjaga integritas data.';
        } else if (effectiveDate > todayStr) {
            impactText = 'Jadwal baru akan aktif mulai tanggal mendatang (' + effectiveDate + '). Sesi kehadiran hari ini dan catatan lampau tetap menggunakan jadwal sebelumnya (tidak diubah).';
        } else {
            impactText = 'Tanggal mulai berlaku berada di masa lampau (' + effectiveDate + '). Tanggal lampau akan ditolak oleh sistem.';
        }

        if (isCross) {
            impactText += ' Catatan: Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in.';
        } else if (shiftSelect.value === '') {
            impactText += ' Catatan: Karyawan tidak memiliki jam shift terjadwal mulai tanggal ini.';
        }

        previewSessionImpact.textContent = impactText;

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    });

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        btnOpen.focus();
    }

    if (btnClose) btnClose.addEventListener('click', closeDialog);
    if (btnBack) btnBack.addEventListener('click', closeDialog);

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Menyimpan...';
            btnOpen.disabled = true;
            form.submit();
        });
    }
})();
</script>
@else
<p class="muted">Penempatan dikelola HR/admin.</p>
@endif
</div></section>
@endif
<section class="panel"><div class="panel-heading"><h2>Riwayat penempatan</h2></div><div class="table-scroll" tabindex="0" role="region" aria-label="Riwayat shift efektif"><table><thead><tr><th>Mulai</th><th>Shift</th><th>Jam / zona</th><th>Pengelola</th><th>Alasan</th></tr></thead><tbody>
@forelse($history as $entry)
<tr><td>{{ $entry->effective_from }}</td><td>{{ $entry->shift_name ?: 'Tanpa shift' }}</td><td>{{ $entry->time_in ?? '—' }}–{{ $entry->time_out ?? '—' }} · {{ $entry->timezone }}</td><td>{{ $entry->actor_name }}<p class="muted">{{ $entry->created_at }}</p></td><td class="permission-reason">{{ $entry->reason }}</td></tr>
@empty
<tr><td colspan="5" class="empty-state">Belum ada riwayat efektif. Penempatan lama tidak diasumsikan sebagai riwayat historis.</td></tr>
@endforelse
</tbody></table></div>@include('components.workspace-pagination',['paginator'=>$history,'unit'=>'penempatan'])</section>
@endsection
