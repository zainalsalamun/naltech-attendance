<dl class="form-grid permission-details">
    <div>
        <dt>Nama karyawan</dt>
        <dd><strong>{{ $permission->user->name }}</strong></dd>
    </div>
    <div>
        <dt>Jabatan & Departemen</dt>
        <dd>{{ $permission->user->position ?: 'Jabatan belum diatur' }} · {{ $permission->user->department ?: 'Departemen belum diatur' }}</dd>
    </div>
    <div>
        <dt>Rentang tanggal</dt>
        <dd>
            <strong>{{ $permission->date_range_label }}</strong>
            @if($permission->start_date && $permission->end_date && $permission->start_date != $permission->end_date)
                <small class="muted" style="display:block">
                    {{ $permission->start_date->format('d M Y') }} s/d {{ $permission->end_date->format('d M Y') }}
                </small>
            @endif
        </dd>
    </div>
    <div>
        <dt>Durasi dihitung</dt>
        <dd>
            <strong style="color:var(--accent)">{{ $permission->duration_days }} hari kerja</strong>
            @if($permission->dates_breakdown)
                <small class="muted" style="display:block">
                    Total {{ count($permission->dates_breakdown) }} hari kalender
                </small>
            @endif
        </dd>
    </div>
    <div>
        <dt>Jenis pengajuan</dt>
        <dd>{{ $permission->leave_policy_snapshot['name'] ?? ($permission->leaveType?->name ?? 'Izin umum / tanpa jenis') }}</dd>
    </div>
    <div>
        <dt>Status pengajuan</dt>
        <dd>
            @php
                $badgeColor = match($permission->status) {
                    'approved' => 'var(--good)',
                    'pending', 'cancellation_requested' => 'var(--amber)',
                    'rejected', 'cancelled' => 'var(--error)',
                    default => 'var(--muted)',
                };
            @endphp
            <span class="badge" style="color:{{ $badgeColor }};font-weight:600">
                {{ $permission->approval_label }}
            </span>
            @if($permission->status === 'cancellation_requested')
                <small style="display:block;color:var(--amber);margin-top:2px">Menunggu keputusan pengelola atas pembatalan. Pengajuan tetap berlaku; saldo terpakai belum dikembalikan.</small>
            @endif
        </dd>
    </div>
</dl>

@if($permission->dates_breakdown)
<div style="margin:20px 0;background:var(--bg);border:1px solid var(--line);border-radius:6px;padding:14px">
    <h3 style="font-size:13px;margin:0 0 10px;display:flex;align-items:center;gap:6px">
        <i class="ion-ios-calendar-outline" aria-hidden="true"></i> Rincian Kalender Tanggal Cuti
    </h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:8px">
        @foreach($permission->dates_breakdown as $day)
            @php $isWork = $day['is_working'] ?? true; @endphp
            <div style="padding:6px 10px;background:var(--panel);border:1px solid var(--line);border-radius:4px;font-size:11px;display:flex;justify-content:space-between;align-items:center">
                <span>{{ $day['date'] }}</span>
                @if($isWork)
                    <span style="color:var(--good);font-weight:500">✓ {{ $day['label'] ?? 'Hari kerja' }}</span>
                @else
                    <span style="color:var(--muted)">— {{ $day['label'] ?? 'Libur' }} (dilewati)</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

@if(!empty($employeeBalance))
<div style="margin:20px 0;background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:14px">
    <h3 style="font-size:13px;margin:0 0 8px;display:flex;align-items:center;gap:6px">
        <i class="ion-ios-pie-outline" aria-hidden="true"></i> Saldo Cuti Karyawan (Periode {{ $employeeBalance->period_year }})
    </h3>
    <p class="muted">Saldo terbaru seluruh pengajuan pada periode ini. Tersedia dapat diajukan; cadangan menunggu keputusan; terpakai sudah disetujui.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(110px, 1fr));gap:12px;text-align:center;font-size:12px">
        <div style="padding:8px;background:var(--bg);border-radius:4px">
            <span class="muted" style="display:block;font-size:11px">Alokasi Total</span>
            <strong>{{ $employeeBalance->allocated_days }} hari</strong>
        </div>
        <div style="padding:8px;background:var(--bg);border-radius:4px">
            <span class="muted" style="display:block;font-size:11px">Cadangan</span>
            <strong style="color:var(--amber)">{{ $employeeBalance->reserved_days }} hari</strong>
        </div>
        <div style="padding:8px;background:var(--bg);border-radius:4px">
            <span class="muted" style="display:block;font-size:11px">Terpakai</span>
            <strong>{{ $employeeBalance->used_days }} hari</strong>
        </div>
        <div style="padding:8px;background:var(--bg);border-radius:4px">
            <span class="muted" style="display:block;font-size:11px">Tersedia</span>
            <strong style="color:var(--good)">{{ $employeeBalance->remaining_days }} hari</strong>
        </div>
    </div>
</div>
@endif

<div class="permission-copy">
    <h2>Alasan Pengajuan</h2>
    <p>{{ $permission->reason ?: 'Alasan belum tersedia.' }}</p>
</div>

@if($permission->cancellation_reason)
<div class="notice" style="margin:16px 0;border-left:4px solid var(--amber)">
    <strong><i class="ion-ios-information-outline" aria-hidden="true"></i> Permintaan Pembatalan Cuti</strong>
    <p style="margin:4px 0 0">{{ $permission->cancellation_reason }}</p>
    @if($permission->cancellation_requested_at)
        <small class="muted">Diajukan pada: {{ $permission->cancellation_requested_at->format('Y-m-d H:i') }}</small>
    @endif
</div>
@endif

<div class="permission-copy">
    <h2>Bukti Pendukung</h2>
    @if($permission->image)
        <a class="secondary" href="{{ route('submission-attachments.show', ['kind' => 'permission', 'id' => $permission->id]) }}" target="_blank" rel="noopener">
            <i class="ion-ios-paper" aria-hidden="true"></i> Buka bukti pendukung <span class="sr-only">di tab baru</span>
        </a>
    @else
        <p class="muted">Tidak ada bukti pendukung.</p>
    @endif
</div>

@if($permission->leave_policy_snapshot)
<div class="permission-copy" style="border-top:1px solid var(--line);padding-top:14px;margin-top:16px">
    <h3>Kebijakan Cuti Saat Diajukan</h3>
    <p><strong>{{ $permission->leave_policy_snapshot['code'] }} · {{ $permission->leave_policy_snapshot['name'] }}</strong></p>
    <p class="muted">
        {{ $permission->leave_policy_snapshot['requires_attachment'] ? 'Lampiran wajib' : 'Lampiran opsional' }} ·
        {{ $permission->leave_policy_snapshot['deducts_balance'] ? 'Mengurangi kuota saldo karyawan' : 'Tidak mengurangi saldo' }}
        @if($permission->leave_policy_snapshot['annual_quota_days'] !== null)
            · Kuota kebijakan: {{ $permission->leave_policy_snapshot['annual_quota_days'] }} hari/tahun
        @endif
    </p>
    @if(!empty($permission->leave_policy_snapshot['requirements']))
        <p class="muted">{{ $permission->leave_policy_snapshot['requirements'] }}</p>
    @endif
</div>
@endif
