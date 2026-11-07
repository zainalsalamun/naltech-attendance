@extends('layouts.workspace')
@section('title', 'Edit kehadiran')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('attendances.index') }}">Kehadiran</a> / Edit</p>
        <h1>Edit kehadiran</h1>
        <p class="muted">{{ $attendance->user->name }} · Tanggal kerja asal: <strong>{{ $attendance->date }}</strong> · Zona waktu: <strong>{{ $attendance->attendance_timezone ?: config('app.timezone') }}</strong></p>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel record-form-panel">
    <div class="panel-heading">
        <div>
            <h2>Koreksi catatan absensi</h2>
            <p class="muted">Perubahan waktu atau lokasi kehadiran akan dicatat ke dalam riwayat audit dengan alasan wajib.</p>
        </div>
    </div>
    <form id="attendance-edit-form" method="post" action="{{ route('attendances.update', $attendance) }}" class="record-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="version" value="{{ $attendance->version ?? 0 }}">
        <input type="hidden" name="concurrency_token" value="{{ $attendance->updated_at?->timestamp }}">

        <div class="form-grid">
            @php
                $tz = $attendance->attendance_timezone ?: config('app.timezone');
                $rawIn = $attendance->checked_in_at;
                $rawOut = $attendance->checked_out_at;
                $defaultCheckinDate = $rawIn ? \Carbon\CarbonImmutable::parse($rawIn, 'UTC')->setTimezone($tz)->toDateString() : $attendance->date;
                $defaultCheckoutDate = $rawOut ? \Carbon\CarbonImmutable::parse($rawOut, 'UTC')->setTimezone($tz)->toDateString() : ($defaultCheckinDate ?: $attendance->date);
            @endphp

            <div class="field">
                <label for="checkin_date">Tanggal masuk aktual</label>
                <input id="checkin_date" name="checkin_date" type="date" value="{{ old('checkin_date', $defaultCheckinDate) }}" required>
            </div>

            <div class="field">
                <label for="checkout_date">Tanggal pulang aktual</label>
                <input id="checkout_date" name="checkout_date" type="date" value="{{ old('checkout_date', $defaultCheckoutDate) }}">
                <small class="muted">Isi tanggal pulang sesuai kejadian sebenarnya; pada shift malam biasanya hari berikutnya.</small>
            </div>

            <div class="field">
                <label for="time_in">Jam masuk (wajib)</label>
                <input id="time_in" name="time_in" type="time" step="1" value="{{ old('time_in', $attendance->time_in) }}" required @error('time_in') aria-invalid="true" aria-describedby="time_in-error" @enderror>
                @error('time_in')<span id="time_in-error" class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="time_out">Jam pulang</label>
                <input id="time_out" name="time_out" type="time" step="1" value="{{ old('time_out', $attendance->time_out) }}" @error('time_out') aria-invalid="true" aria-describedby="time_out-error" @enderror>
                @error('time_out')<span id="time_out-error" class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="latlon_in">Koordinat masuk (wajib)</label>
                <input id="latlon_in" name="latlon_in" type="text" value="{{ old('latlon_in', $attendance->latlon_in) }}" required placeholder="-6.200000,106.816666" @error('latlon_in') aria-invalid="true" aria-describedby="latlon_in-error" @enderror>
                @error('latlon_in')<span id="latlon_in-error" class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="latlon_out">Koordinat pulang</label>
                <input id="latlon_out" name="latlon_out" type="text" value="{{ old('latlon_out', $attendance->latlon_out) }}" placeholder="-6.200000,106.816666" @error('latlon_out') aria-invalid="true" aria-describedby="latlon_out-error" @enderror>
                @error('latlon_out')<span id="latlon_out-error" class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="correction_reason">Alasan koreksi (wajib)</label>
                <textarea id="correction_reason" name="correction_reason" required maxlength="1000" rows="3" placeholder="Tuliskan alasan mengapa data kehadiran ini disesuaikan...">{{ old('correction_reason') }}</textarea>
                <small class="muted">Alasan koreksi akan dicatat permanen dalam riwayat audit bersama nama Anda sebagai pengoreksi.</small>
            </div>
        </div>

        <p class="muted" style="margin-top:14px">
            Tanggal kerja asal ({{ $attendance->date }}) dan salinan jadwal tidak diubah. Koreksi mengikuti akses pengelola perusahaan.
        </p>

        <div class="dialog-actions" style="margin-top:16px">
            <a class="secondary" href="{{ route('attendances.index') }}">Batal</a>
            <button class="primary" type="button" id="btn-open-preview">Tinjau koreksi & Simpan perubahan</button>
        </div>
    </form>
</section>

{{-- Modal Pratinjau Keputusan Koreksi --}}
<dialog id="correction-preview-dialog" class="attendance-dialog" aria-labelledby="preview-dialog-title" style="max-width:540px;width:92%;padding:20px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);box-shadow:var(--shadow-lg);">
    <div class="dialog-heading" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
        <h2 id="preview-dialog-title" style="margin:0;font-size:18px;">Tinjau Koreksi Kehadiran</h2>
        <button type="button" id="preview-dialog-close" class="secondary" style="padding:4px 8px;font-size:12px;">✕ Tutup</button>
    </div>
    <div class="dialog-content" style="font-size:13px;display:flex;flex-direction:column;gap:12px">
        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line)">
            <div><strong>Karyawan:</strong> {{ $attendance->user->name }}</div>
            <div><strong>Tanggal Kerja Asal:</strong> {{ $attendance->date }}</div>
            <div><strong>Zona Waktu:</strong> {{ $attendance->attendance_timezone ?: config('app.timezone') }}</div>
        </div>

        <table style="width:100%;font-size:13px;border-collapse:collapse;margin-top:4px">
            <thead>
                <tr style="border-bottom:1px solid var(--line);text-align:left">
                    <th style="padding:6px 4px">Field</th>
                    <th style="padding:6px 4px">Sebelum</th>
                    <th style="padding:6px 4px">Sesudah (Koreksi)</th>
                    <th style="padding:6px 4px;text-align:right">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Jam Masuk</td>
                    <td style="padding:6px 4px"><code>{{ $attendance->time_in ?: '—' }}</code></td>
                    <td style="padding:6px 4px"><strong><code id="preview-after-in">—</code></strong></td>
                    <td style="padding:6px 4px;text-align:right"><span id="badge-diff-in" class="badge">—</span></td>
                </tr>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Jam Pulang</td>
                    <td style="padding:6px 4px"><code>{{ $attendance->time_out ?: '—' }}</code></td>
                    <td style="padding:6px 4px"><strong><code id="preview-after-out">—</code></strong></td>
                    <td style="padding:6px 4px;text-align:right"><span id="badge-diff-out" class="badge">—</span></td>
                </tr>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Tanggal Masuk / Pulang</td>
                    <td style="padding:6px 4px"><small class="muted">{{ $defaultCheckinDate }} / {{ $defaultCheckoutDate }}</small></td>
                    <td style="padding:6px 4px"><strong><small id="preview-after-dates">—</small></strong></td>
                    <td style="padding:6px 4px;text-align:right"><span id="badge-diff-dates" class="badge">—</span></td>
                </tr>
                <tr style="border-bottom:1px solid var(--line)">
                    <td style="padding:6px 4px" class="muted">Koordinat Masuk</td>
                    <td style="padding:6px 4px"><small>{{ $attendance->latlon_in }}</small></td>
                    <td style="padding:6px 4px"><strong><small id="preview-after-latlon-in">—</small></strong></td>
                    <td style="padding:6px 4px;text-align:right"><span id="badge-diff-latlon-in" class="badge">—</span></td>
                </tr>
                <tr>
                    <td style="padding:6px 4px" class="muted">Koordinat Pulang</td>
                    <td style="padding:6px 4px"><small>{{ $attendance->latlon_out ?: '—' }}</small></td>
                    <td style="padding:6px 4px"><strong><small id="preview-after-latlon-out">—</small></strong></td>
                    <td style="padding:6px 4px;text-align:right"><span id="badge-diff-latlon-out" class="badge">—</span></td>
                </tr>
            </tbody>
        </table>

        <div>
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Alasan Koreksi</div>
            <div id="preview-reason-text" style="background:var(--bg);padding:8px 10px;border-radius:4px;border:1px solid var(--line);white-space:pre-wrap;color:var(--ink);font-size:13px"></div>
        </div>

        <p class="muted" style="margin:4px 0 0;font-size:12px">
            Perubahan dan alasan koreksi akan disimpan dalam riwayat. Pastikan seluruh detail telah sesuai sebelum konfirmasi.
        </p>
    </div>
    <div class="dialog-actions" style="margin-top:18px;display:flex;justify-content:flex-end;gap:8px">
        <button type="button" id="preview-dialog-back" class="secondary">Kembali mengedit</button>
        <button type="button" id="preview-dialog-confirm" class="primary">Konfirmasi koreksi</button>
    </div>
</dialog>

<section class="panel" style="margin-top:24px">
    <div class="panel-heading">
        <div>
            <h2>Riwayat Audit Koreksi Kehadiran</h2>
            <p class="muted">Jejak perubahan jam masuk, pulang, dan alasan penyesuaian oleh pengelola.</p>
        </div>
    </div>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Riwayat audit koreksi catatan kehadiran ini">
        <table>
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Pengoreksi</th>
                    <th scope="col">Alasan Koreksi</th>
                    <th scope="col">Perubahan (Sebelum → Sesudah)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendance->correctionEvents as $event)
                    @php
                        $snapshot = $event->snapshot ?? [];
                        $before = $snapshot['before'] ?? [];
                        $after = $snapshot['after'] ?? [];
                    @endphp
                    <tr>
                        <td class="time">{{ $event->created_at ? $event->created_at->format('Y-m-d H:i') : '—' }}</td>
                        <td>{{ $event->actor?->name ?? 'Pengelola' }}</td>
                        <td class="permission-reason">{{ $event->reason }}</td>
                        <td>
                            <div style="font-size:12px;display:flex;flex-direction:column;gap:4px">
                                <div>
                                    <span class="muted">Masuk:</span> 
                                    <code>{{ $before['time_in'] ?? '—' }}</code> → <strong><code>{{ $after['time_in'] ?? '—' }}</code></strong>
                                </div>
                                <div>
                                    <span class="muted">Pulang:</span> 
                                    <code>{{ $before['time_out'] ?? '—' }}</code> → <strong><code>{{ $after['time_out'] ?? '—' }}</code></strong>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">
                            <strong>Belum ada riwayat koreksi untuk catatan ini</strong>
                            <p>Catatan ini masih mempertahankan data asli atau belum pernah disesuaikan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
(function() {
    var form = document.getElementById('attendance-edit-form');
    var dialog = document.getElementById('correction-preview-dialog');
    var btnOpen = document.getElementById('btn-open-preview');
    var btnClose = document.getElementById('preview-dialog-close');
    var btnBack = document.getElementById('preview-dialog-back');
    var btnConfirm = document.getElementById('preview-dialog-confirm');

    var reasonInput = document.getElementById('correction_reason');
    var timeInInput = document.getElementById('time_in');
    var timeOutInput = document.getElementById('time_out');
    var checkinDateInput = document.getElementById('checkin_date');
    var checkoutDateInput = document.getElementById('checkout_date');
    var latlonInInput = document.getElementById('latlon_in');
    var latlonOutInput = document.getElementById('latlon_out');

    var previewAfterIn = document.getElementById('preview-after-in');
    var previewAfterOut = document.getElementById('preview-after-out');
    var previewAfterDates = document.getElementById('preview-after-dates');
    var previewAfterLatlonIn = document.getElementById('preview-after-latlon-in');
    var previewAfterLatlonOut = document.getElementById('preview-after-latlon-out');
    var previewReason = document.getElementById('preview-reason-text');

    var badgeDiffIn = document.getElementById('badge-diff-in');
    var badgeDiffOut = document.getElementById('badge-diff-out');
    var badgeDiffDates = document.getElementById('badge-diff-dates');
    var badgeDiffLatlonIn = document.getElementById('badge-diff-latlon-in');
    var badgeDiffLatlonOut = document.getElementById('badge-diff-latlon-out');

    var origIn = @json($attendance->time_in ?? '');
    var origOut = @json($attendance->time_out ?? '');
    var origCheckinDate = @json($defaultCheckinDate);
    var origCheckoutDate = @json($defaultCheckoutDate);
    var origLatlonIn = @json($attendance->latlon_in ?? '');
    var origLatlonOut = @json($attendance->latlon_out ?? '');

    function normalizeTime(val) {
        if (!val) return '';
        var parts = val.trim().split(':');
        if (parts.length >= 2) {
            return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
        }
        return val.trim();
    }

    function setBadge(el, isChanged) {
        if (!el) return;
        if (isChanged) {
            el.textContent = 'Diubah';
            el.style.background = 'var(--subtle)';
            el.style.color = 'var(--accent)';
            el.style.borderColor = 'var(--line)';
            el.style.fontWeight = '600';
            el.style.opacity = '1';
        } else {
            el.textContent = 'Tidak berubah';
            el.style.background = 'transparent';
            el.style.color = 'var(--muted)';
            el.style.borderColor = 'transparent';
            el.style.fontWeight = 'normal';
            el.style.opacity = '0.7';
        }
    }

    var lastFocused = null;
    var isSubmitting = false;

    function openDialog() {
        var reason = (reasonInput.value || '').trim();
        if (!reason) {
            alert('Alasan koreksi wajib diisi sebelum meninjau atau menyimpan.');
            reasonInput.focus();
            return;
        }

        if (!timeInInput.value) {
            alert('Jam masuk wajib diisi.');
            timeInInput.focus();
            return;
        }

        if (!latlonInInput.value) {
            alert('Koordinat masuk wajib diisi.');
            latlonInInput.focus();
            return;
        }

        // Safe textContent assignment (XSS protection)
        previewAfterIn.textContent = timeInInput.value || '—';
        previewAfterOut.textContent = timeOutInput.value || '—';
        previewAfterDates.textContent = (checkinDateInput.value || '—') + ' / ' + (checkoutDateInput.value || '—');
        previewAfterLatlonIn.textContent = latlonInInput.value || '—';
        previewAfterLatlonOut.textContent = latlonOutInput.value || '—';
        previewReason.textContent = reason;

        var changedIn = normalizeTime(timeInInput.value) !== normalizeTime(origIn);
        var changedOut = normalizeTime(timeOutInput.value) !== normalizeTime(origOut);
        var changedDates = (checkinDateInput.value || '') !== origCheckinDate || (checkoutDateInput.value || '') !== origCheckoutDate;
        var changedLatlonIn = (latlonInInput.value || '').trim() !== (origLatlonIn || '').trim();
        var changedLatlonOut = (latlonOutInput.value || '').trim() !== (origLatlonOut || '').trim();

        setBadge(badgeDiffIn, changedIn);
        setBadge(badgeDiffOut, changedOut);
        setBadge(badgeDiffDates, changedDates);
        setBadge(badgeDiffLatlonIn, changedLatlonIn);
        setBadge(badgeDiffLatlonOut, changedLatlonOut);

        lastFocused = document.activeElement;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        btnBack.focus();
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        isSubmitting = false;
        if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.textContent = 'Konfirmasi koreksi';
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    if (btnOpen) btnOpen.addEventListener('click', openDialog);
    if (btnClose) btnClose.addEventListener('click', closeDialog);
    if (btnBack) btnBack.addEventListener('click', closeDialog);

    if (dialog) {
        dialog.addEventListener('cancel', function() {
            setTimeout(function() {
                if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            }, 0);
        });
    }

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            if (isSubmitting) return;
            isSubmitting = true;
            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Memproses...';
            form.submit();
        });
    }
})();
</script>
@endsection
