@php
    $viewer = auth()->user();
    $approvalService = app(\App\Services\SubmissionApproval::class);
    $workflowService = app(\App\Services\ApprovalWorkflow::class);

    $canDecide = $viewer ? $approvalService->canDecide($viewer, $submission) : false;
    $actionType = $viewer ? $approvalService->actionType($viewer, $submission) : null;
    $whyCannotDecide = $viewer ? $approvalService->whyCannotDecide($viewer, $submission) : null;
    $isOwner = $viewer && ((int) $submission->user_id === (int) $viewer->id);

    $activeApprover = !empty($submission->approval_steps) ? $workflowService->reviewer($submission) : null;

    $applicantName = $submission->user?->name ?? 'Karyawan';
    $applicantDept = $submission->user?->department ?: 'Departemen belum diatur';

    $moduleLabel = match($kind) {
        'permission' => ($submission->leave_policy_snapshot['name'] ?? ($submission->leaveType?->name ?? 'Izin')) . ': ' . $submission->date_range_label,
        'overtime' => 'Lembur: ' . $submission->date . ' (' . $submission->duration . ' menit)',
        'reimbursement' => 'Reimbursement: ' . $submission->date . ' (Rp ' . number_format($submission->amount, 2, ',', '.') . ')',
        default => ucfirst($kind),
    };

    $company = $submission->relationLoaded('user') ? $submission->user?->company : ($submission->user_id ? \App\Models\User::find($submission->user_id)?->company : null);
    $companyTz = $company ? app(\App\Services\WorkCalendar::class)->timezone($company) : config('app.timezone');

    $previews = [
        'cancellation' => [
            'approved' => $approvalService->impactPreview($kind, $submission, 'approved'),
            'rejected' => $approvalService->impactPreview($kind, $submission, 'rejected'),
        ],
        'approval' => [
            'approved' => $approvalService->impactPreview($kind, $submission, 'approved'),
            'rejected' => $approvalService->impactPreview($kind, $submission, 'rejected'),
        ],
    ];

    $statusBadgeClass = match($submission->status) {
        'approved' => 'status-ontime',
        'pending' => 'amber',
        'rejected', 'cancelled' => 'error',
        'cancellation_requested' => 'amber',
        default => 'muted',
    };
@endphp

<section class="panel record-form-panel approval-panel">
    <div class="panel-heading">
        <div>
            <h2>Keputusan dan Riwayat Persetujuan</h2>
            <p class="muted">Tinjau status berjalan, kewenangan peninjau, dan jejak keputusan.</p>
        </div>
    </div>

    <div class="record-form">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
            <span style="font-weight:500">Status pengajuan:</span>
            <span class="badge {{ $statusBadgeClass }}" style="font-weight:600">
                {{ $submission->approval_label }}
            </span>
        </div>

        @if(!empty($submission->approval_steps))
            <div style="background:var(--bg);padding:12px 14px;border-radius:6px;border:1px solid var(--line);margin-bottom:16px;font-size:13px">
                <div style="color:var(--muted);margin-bottom:4px">
                    Alur persetujuan: {{ implode(' → ', array_column($submission->approval_steps, 'label')) }}
                </div>
                @if($submission->status === 'pending')
                    <div style="font-weight:600;color:var(--accent)">
                        Tahap {{ (int) $submission->approval_step + 1 }} dari {{ count($submission->approval_steps) }}: {{ $submission->approval_steps[$submission->approval_step]['label'] ?? 'Pemeriksaan' }}
                    </div>
                    @if($activeApprover)
                        <div style="color:var(--ink);margin-top:3px">
                            Peninjau saat ini: <strong>{{ $activeApprover->name }}</strong> ({{ $activeApprover->email }})
                        </div>
                    @else
                        <div class="error" style="margin-top:4px;font-weight:500">
                            Peninjau belum tersedia; hubungi HR untuk penyesuaian alur atau atasan langsung.
                        </div>
                    @endif
                @endif
            </div>
        @endif

        @if($submission->approval_legacy)
            <div class="notice" style="margin-bottom:14px">
                <strong>Data Legacy</strong>
                <p style="margin:4px 0">Peninjau dan waktu keputusan sebelumnya belum tercatat lengkap. Data bertanda "Perlu ditinjau" memerlukan rekonsiliasi oleh HR/Admin.</p>
            </div>
        @endif

        @if($submission->reviewed_at)
            @php
                $formattedReviewedAt = \Carbon\CarbonImmutable::parse($submission->reviewed_at, config('app.timezone'))->setTimezone($companyTz)->format('Y-m-d H:i:s');
            @endphp
            <p class="muted" style="margin-bottom:14px">
                Keputusan terakhir: {{ $formattedReviewedAt }} ({{ $companyTz }}) · Catatan: {{ $submission->decision_reason ?: 'Tanpa catatan' }}
            </p>
        @endif

        {{-- Scenario 1: Reviewing a cancellation request --}}
        @if($actionType === 'cancellation')
            <div class="notice" style="border-left:4px solid var(--amber);margin-bottom:16px">
                <strong><i class="ion-ios-alert" aria-hidden="true"></i> Keputusan Pembatalan</strong>
                <p style="margin:4px 0">Permohonan pembatalan dari pemohon sedang ditinjau. Pengajuan tetap berlaku dan kuota cuti belum dikembalikan sampai permohonan disetujui.</p>
                <p style="margin:4px 0;color:var(--muted)">Alasan pemohon: "{{ $submission->cancellation_reason ?: $submission->decision_reason }}"</p>
            </div>

            <form id="submission-cancellation-form" method="post" action="{{ route('submission-approvals.decision', ['kind' => $kind, 'id' => $submission->id]) }}">
                @csrf
                <input type="hidden" name="approval_version" value="{{ $submission->approval_version }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="cancel-decision-status">Tindakan Pembatalan</label>
                        <select id="cancel-decision-status" name="status" required>
                            <option value="approved">Setujui Pembatalan</option>
                            <option value="rejected">Tolak Pembatalan</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="cancel-decision-reason">Alasan Keputusan HR / Atasan (Wajib)</label>
                        <textarea id="cancel-decision-reason" name="decision_reason" required maxlength="1000" rows="3" placeholder="Tuliskan alasan persetujuan atau penolakan pembatalan ini...">{{ old('decision_reason') }}</textarea>
                    </div>
                </div>
                <div class="dialog-actions" style="margin-top:12px">
                    <button class="primary" type="button" id="btn-review-cancellation">Tinjau keputusan pembatalan</button>
                </div>
            </form>

        {{-- Scenario 2: Active reviewer deciding on pending / needs_review --}}
        @elseif($actionType === 'approval')
            @php
                $isMultiStep = !empty($submission->approval_steps);
                $isFinalStep = $isMultiStep ? ((int) $submission->approval_step + 1 >= count($submission->approval_steps)) : true;
                $approveOptionLabel = $isFinalStep ? 'Setujui Pengajuan (Final)' : 'Setujui Tahap Ini';
            @endphp
            <form id="submission-decision-form" method="post" action="{{ route('submission-approvals.decision', ['kind' => $kind, 'id' => $submission->id]) }}">
                @csrf
                <input type="hidden" name="approval_version" value="{{ $submission->approval_version }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="decision-status">Tindakan Keputusan</label>
                        <select id="decision-status" name="status" required>
                            <option value="approved" @selected(old('status') === 'approved')>{{ $approveOptionLabel }}</option>
                            <option value="rejected" @selected(old('status') === 'rejected')>Tolak Pengajuan</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="decision-reason">Alasan Keputusan (Wajib)</label>
                        <textarea id="decision-reason" name="decision_reason" required maxlength="1000" rows="3" placeholder="Tuliskan catatan atau alasan keputusan Anda...">{{ old('decision_reason') }}</textarea>
                    </div>
                </div>
                <p class="muted" style="margin-top:6px">
                    {{ $isFinalStep ? 'Persetujuan final akan menyelesaikan seluruh alur pengajuan.' : 'Persetujuan akan meneruskan pengajuan ke tahap peninjau berikutnya.' }}
                    Penolakan akan mengakhiri pengajuan dan mengembalikan saldo cadangan jika ada.
                </p>
                <div class="dialog-actions" style="margin-top:12px">
                    <button class="primary" type="button" id="btn-review-decision">Tinjau keputusan</button>
                </div>
            </form>

        {{-- Scenario 4: Owner actions and notices --}}
        @elseif($isOwner)
            @if($submission->status === 'pending')
                <div class="notice" style="margin-bottom:12px">
                    <strong>Batalkan Pengajuan</strong>
                    <p style="margin:4px 0">Pengajuan Anda sedang menunggu peninjauan. Anda dapat membatalkannya secara langsung selama status masih menunggu.</p>
                </div>
                <form method="post" action="{{ route('submission-approvals.cancel', ['kind' => $kind, 'id' => $submission->id]) }}" onsubmit="return confirm('Batalkan pengajuan ini?');">
                    @csrf
                    <input type="hidden" name="approval_version" value="{{ $submission->approval_version }}">
                    <div class="field" style="margin-bottom:12px">
                        <label for="cancel-reason">Alasan Pembatalan (Wajib)</label>
                        <textarea id="cancel-reason" name="decision_reason" maxlength="1000" required rows="3" placeholder="Jelaskan alasan Anda membatalkan pengajuan ini..."></textarea>
                    </div>
                    <div class="dialog-actions">
                        <button class="danger" type="submit">Batalkan Pengajuan</button>
                    </div>
                </form>
            @elseif($kind === 'permission' && $submission->status === 'approved')
                <div class="notice" style="margin-bottom:12px">
                    <strong><i class="ion-ios-information-outline" aria-hidden="true"></i> Permohonan Pembatalan Cuti</strong>
                    <p style="margin:4px 0">Pengajuan ini telah disetujui. Jika Anda berhalangan atau membatalkan cuti, ajukan permohonan pembatalan kepada HR agar kuota saldo cuti dapat dikembalikan.</p>
                </div>
                <form method="post" action="{{ route('submission-approvals.cancel', ['kind' => $kind, 'id' => $submission->id]) }}" onsubmit="return confirm('Kirim permohonan pembatalan cuti ke HR?');">
                    @csrf
                    <input type="hidden" name="approval_version" value="{{ $submission->approval_version }}">
                    <input type="hidden" name="request_cancellation" value="1">
                    <div class="field" style="margin-bottom:12px">
                        <label for="cancel-reason-approved">Alasan Permohonan Pembatalan (Wajib)</label>
                        <textarea id="cancel-reason-approved" name="decision_reason" maxlength="1000" required rows="3" placeholder="Tuliskan alasan mengapa cuti ini perlu dibatalkan..."></textarea>
                    </div>
                    <div class="dialog-actions">
                        <button class="secondary" style="border-color:var(--amber);color:var(--amber)" type="submit">Ajukan Pembatalan ke HR</button>
                    </div>
                </form>
            @elseif($submission->status === 'cancellation_requested')
                <div class="notice" style="border-left:4px solid var(--amber);margin-bottom:12px">
                    <strong>Permohonan Pembatalan Sedang Diproses</strong>
                    <p style="margin:4px 0">Permohonan pembatalan Anda sedang menunggu keputusan pengelola. Pengajuan tetap berlaku dan saldo terpakai belum dikembalikan.</p>
                </div>
            @else
                <p class="muted">Pengajuan Anda telah selesai ({{ $submission->approval_label }}).</p>
            @endif

        {{-- Scenario 3: Non-owner Read-Only Message --}}
        @else
            @if($submission->status === 'cancellation_requested')
                <div class="notice" style="border-left:4px solid var(--amber);margin-bottom:12px">
                    <strong>Permohonan Pembatalan Sedang Berjalan</strong>
                    <p style="margin:4px 0">Permohonan pembatalan dari pemohon sedang ditinjau. Pengajuan tetap berlaku sampai pembatalan disetujui.</p>
                    <p style="margin:4px 0;color:var(--muted)">Alasan pemohon: "{{ $submission->cancellation_reason ?: $submission->decision_reason }}"</p>
                </div>
                @if($whyCannotDecide)
                    <p class="muted">{{ $whyCannotDecide }}</p>
                @endif
            @elseif($submission->status === 'pending')
                @if(!empty($submission->approval_steps) && !$activeApprover)
                    <div class="notice" style="border-left:4px solid var(--error);margin-bottom:12px">
                        <strong>Peninjau Tidak Tersedia</strong>
                        <p style="margin:4px 0">Peninjau belum tersedia; hubungi HR untuk penyesuaian alur atau atasan langsung.</p>
                    </div>
                @else
                    <p class="muted">{{ $whyCannotDecide ?: 'Pengajuan sedang menunggu persetujuan dari peninjau tahap aktif.' }}</p>
                @endif
            @elseif($submission->status === 'needs_review')
                <p class="muted">{{ $whyCannotDecide ?: 'Pengajuan data lama ini memerlukan rekonsiliasi oleh HR/Admin.' }}</p>
            @else
                <p class="muted">Pengajuan ini telah berstatus final ({{ $submission->approval_label }}). Riwayat keputusan disimpan permanen untuk audit.</p>
            @endif
        @endif
    </div>

    {{-- Riwayat Keputusan Table --}}
    <div class="table-scroll" tabindex="0" role="region" aria-label="Riwayat keputusan pengajuan" style="margin-top:16px">
        <table>
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Aktor</th>
                    <th scope="col">Peristiwa</th>
                    <th scope="col">Catatan & Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($approvalEvents as $event)
                    @php
                        $classified = $event->classified ?? $approvalService->classifyEvent($event);
                        $badgeClass = $classified['badge'] ?? 'muted';
                        $actionLabel = $classified['action_label'] ?? ($event->action_label ?? 'Perubahan status');
                        $description = $classified['description'] ?? null;
                    @endphp
                    <tr>
                        @php
                            $eventTime = \Carbon\CarbonImmutable::parse($event->created_at, config('app.timezone'))->setTimezone($companyTz)->format('Y-m-d H:i:s');
                        @endphp
                        <td class="time">{{ $eventTime }} ({{ $companyTz }})</td>
                        <td><strong>{{ $event->actor_name ?: 'Sistem' }}</strong></td>
                        <td>
                            <div>
                                <span class="badge {{ $badgeClass }}" style="font-weight:500">
                                    {{ $actionLabel }}
                                </span>
                            </div>
                            @if($description)
                                <small class="muted" style="display:block;margin-top:3px">
                                    {{ $description }}
                                </small>
                            @endif
                        </td>
                        <td class="permission-reason">
                            {{ $event->reason ?: 'Tanpa catatan' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">Belum ada perubahan keputusan yang tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- Tinjau Keputusan Dialog --}}
<dialog id="decision-review-dialog" class="attendance-dialog" aria-labelledby="decision-review-title">
    <div class="dialog-heading">
        <h2 id="decision-review-title">Tinjau Keputusan</h2>
        <button type="button" id="review-dialog-close" class="secondary" aria-label="Tutup pratinjau keputusan">✕</button>
    </div>
    <div class="dialog-content" style="margin-top:14px">
        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line);margin-bottom:12px">
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Pemohon</div>
            <div style="font-weight:600;font-size:14px" id="review-applicant-name">{{ $applicantName }}</div>
            <div style="font-size:12px;color:var(--muted)" id="review-applicant-dept">{{ $applicantDept }}</div>
        </div>

        <div style="margin-bottom:12px">
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Pengajuan</div>
            <div style="font-weight:500;font-size:13px" id="review-module-desc">{{ $moduleLabel }}</div>
        </div>

        <div style="margin-bottom:12px">
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Keputusan yang Dipilih</div>
            <div style="font-weight:600;font-size:14px;margin-top:2px" id="review-decision-text"></div>
        </div>

        <div style="margin-bottom:12px">
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Alasan Keputusan</div>
            <div id="review-reason-text" style="margin-top:3px;background:var(--panel);padding:8px 10px;border-radius:4px;border:1px solid var(--line);white-space:pre-wrap;font-size:13px;color:var(--ink)"></div>
        </div>

        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border-left:4px solid var(--accent);margin-top:14px">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--accent);margin-bottom:3px">Dampak Keputusan</div>
            <div id="review-impact-text" style="font-size:13px;line-height:1.4"></div>
        </div>
        <p class="muted" style="margin-top:12px;font-size:12px">Periksa kembali detail di atas sebelum konfirmasi. Simpan keputusan akan memperbarui status dan alur pengajuan.</p>
    </div>
    <div class="dialog-actions" style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px">
        <button type="button" id="review-dialog-back" class="secondary">Kembali mengedit</button>
        <button type="button" id="review-dialog-confirm" class="primary">Konfirmasi keputusan</button>
    </div>
</dialog>

<script>
(function() {
    var previews = @json($previews);
    var dialog = document.getElementById('decision-review-dialog');
    var btnClose = document.getElementById('review-dialog-close');
    var btnBack = document.getElementById('review-dialog-back');
    var btnConfirm = document.getElementById('review-dialog-confirm');

    var decisionTextEl = document.getElementById('review-decision-text');
    var reasonTextEl = document.getElementById('review-reason-text');
    var impactTextEl = document.getElementById('review-impact-text');

    var currentForm = null;
    var lastFocused = null;

    var isSubmitting = false;

    function openReview(formType, formEl, statusEl, reasonEl) {
        var status = statusEl.value;
        var reason = reasonEl.value.trim();

        if (!reason) {
            alert('Alasan keputusan wajib diisi sebelum meninjau atau menyimpan.');
            reasonEl.focus();
            return;
        }

        var config = (previews[formType] && previews[formType][status]) || {
            decision_label: status === 'approved' ? 'Setujui' : 'Tolak',
            impact: 'Status pengajuan akan diperbarui sesuai keputusan.'
        };

        decisionTextEl.textContent = config.decision_label;
        reasonTextEl.textContent = reason;
        impactTextEl.textContent = config.impact;

        currentForm = formEl;
        lastFocused = document.activeElement;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
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
            btnConfirm.textContent = 'Konfirmasi keputusan';
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    if (dialog) {
        dialog.addEventListener('cancel', function() {
            setTimeout(function() {
                if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            }, 0);
        });
    }

    if (btnClose) btnClose.addEventListener('click', closeDialog);
    if (btnBack) btnBack.addEventListener('click', closeDialog);

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            if (!currentForm || isSubmitting) return;
            isSubmitting = true;
            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Memproses...';
            currentForm.submit();
        });
    }

    var btnReviewDecision = document.getElementById('btn-review-decision');
    if (btnReviewDecision) {
        btnReviewDecision.addEventListener('click', function() {
            var form = document.getElementById('submission-decision-form');
            var statusEl = document.getElementById('decision-status');
            var reasonEl = document.getElementById('decision-reason');
            openReview('approval', form, statusEl, reasonEl);
        });
    }

    var btnReviewCancel = document.getElementById('btn-review-cancellation');
    if (btnReviewCancel) {
        btnReviewCancel.addEventListener('click', function() {
            var form = document.getElementById('submission-cancellation-form');
            var statusEl = document.getElementById('cancel-decision-status');
            var reasonEl = document.getElementById('cancel-decision-reason');
            openReview('cancellation', form, statusEl, reasonEl);
        });
    }
})();
</script>
