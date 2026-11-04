<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SubmissionApproval
{
    public static function model(string $kind): string
    {
        return match ($kind) {
            'permission' => Permission::class,'overtime' => Overtime::class,'reimbursement' => Reimbursement::class,default => abort(404)
        };
    }

    public function history(string $kind, Model $row)
    {
        $row->setRelation('activeApprover', app(ApprovalWorkflow::class)->reviewer($row));

        $companyId = $row->user?->company_id ?? request()->user()?->company_id;

        return DB::table('submission_approval_events as e')
            ->leftJoin('users as actor', 'actor.id', '=', 'e.actor_id')
            ->where('e.company_id', $companyId)
            ->where('e.kind', $kind)
            ->where('e.submission_id', $row->id)
            ->orderByDesc('e.id')
            ->select('e.*', 'actor.name as actor_name')
            ->limit(50)
            ->get()
            ->map(function ($event) {
                $event->classified = self::classifyEvent($event);
                return $event;
            });
    }

    public function detail(string $kind, Model $row): Model
    {
        $viewer = request()->user();
        $events = $this->history($kind, $row);
        $row->setAttribute('approval_current_reviewer', $row->activeApprover?->name);
        if ($viewer) {
            $row->setAttribute('can_decide', $this->canDecide($viewer, $row));
            $row->setAttribute('action_type', $this->actionType($viewer, $row));
            $row->setAttribute('cannot_decide_reason', $this->whyCannotDecide($viewer, $row));
        }
        $row->unsetRelation('activeApprover');

        $company = $row->relationLoaded('user') ? $row->user?->company : ($row->user_id ? \App\Models\User::find($row->user_id)?->company : null);
        $company = $company ?? $viewer?->company;
        $tz = $company ? app(\App\Services\WorkCalendar::class)->timezone($company) : config('app.timezone');

        $row->setAttribute('timezone', $tz);
        $row->setAttribute('company_timezone', $tz);
        $row->setAttribute('approval_history', $events->map(function ($event) use ($tz) {
            $classified = $event->classified ?? self::classifyEvent($event);
            $snapshot = json_decode($event->snapshot, true);

            return [
                'actor' => $event->actor_name,
                'time' => \Carbon\CarbonImmutable::parse($event->created_at, config('app.timezone'))->setTimezone($tz)->toIso8601String(),
                'reason' => $event->reason,
                'stage' => ! empty($snapshot['before']['approval_steps']) ? (int) ($snapshot['before']['approval_step'] ?? 0) + 1 : null,
                'action' => $classified['action'],
                'action_label' => $classified['action_label'],
                'event_type' => $classified['event_type'],
            ];
        })->values()->all());

        return $row;
    }

    public function canDecide(User $actor, Model $submission): bool
    {
        if (! $actor->isEmploymentActive()) {
            return false;
        }

        $owner = $submission->relationLoaded('user') ? $submission->user : $submission->user()->first();
        if (! $owner) {
            return false;
        }

        if ((int) $owner->company_id !== (int) $actor->company_id) {
            return false;
        }

        // Rule: Self-approval is strictly forbidden
        if ((int) $owner->id === (int) $actor->id) {
            return false;
        }

        $status = $submission->status;

        if ($status === 'cancellation_requested') {
            if (! ($submission instanceof Permission)) {
                return false;
            }

            return in_array($actor->role, ['admin', 'super admin', 'hrd'], true)
                || ($actor->role === 'supervisor' && (int) $owner->manager_id === (int) $actor->id);
        }

        if ($status === 'needs_review') {
            return false;
        }

        if ($status === 'pending') {
            if (! empty($submission->approval_steps)) {
                $reviewer = app(ApprovalWorkflow::class)->reviewer($submission);
                return $reviewer !== null && (int) $reviewer->id === (int) $actor->id;
            }

            // Single-decision without workflow
            return in_array($actor->role, ['admin', 'super admin', 'hrd'], true)
                || ($actor->role === 'supervisor' && (int) $owner->manager_id === (int) $actor->id);
        }

        return false;
    }

    public function whyCannotDecide(User $actor, Model $submission): ?string
    {
        if (! $actor->isEmploymentActive()) {
            return 'Akun Anda saat ini berstatus nonaktif sehingga tidak dapat mengambil keputusan.';
        }

        $owner = $submission->relationLoaded('user') ? $submission->user : $submission->user()->first();
        if (! $owner || (int) $owner->company_id !== (int) $actor->company_id) {
            return 'Pengajuan berada di luar organisasi/perusahaan akun Anda.';
        }

        if ((int) $owner->id === (int) $actor->id) {
            return 'Pengajuan ini adalah milik Anda sendiri. Sesuai kebijakan tata kelola, pengajuan sendiri harus ditinjau oleh pengelola lain.';
        }

        $status = $submission->status;

        if (in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            $label = $submission->approval_label ?? ucfirst($status);
            return "Pengajuan ini telah berstatus final ({$label}). Riwayat keputusan disimpan permanen untuk audit.";
        }

        if ($status === 'cancellation_requested') {
            if ($actor->role === 'supervisor') {
                return 'Permintaan pembatalan hanya dapat disetujui oleh atasan langsung pemohon atau HR/Admin.';
            }
            return 'Permintaan pembatalan sedang menunggu keputusan atasan langsung atau HR/Admin.';
        }

        if ($status === 'needs_review') {
            return 'Pengajuan data lama ini berstatus "Perlu ditinjau" dan memerlukan rekonsiliasi oleh HR/Admin, bukan melalui alur persetujuan operasional normal.';
        }

        if ($status === 'pending') {
            if (! empty($submission->approval_steps)) {
                $reviewer = app(ApprovalWorkflow::class)->reviewer($submission);
                $step = ($submission->approval_steps ?? [])[$submission->approval_step] ?? [];
                $stepNumber = (int) ($submission->approval_step ?? 0) + 1;
                $stepLabel = $step['label'] ?? "Tahap {$stepNumber}";

                if (! $reviewer) {
                    return 'Peninjau belum tersedia untuk tahap ini; hubungi pengelola sistem/HR.';
                }

                return "Pengajuan saat ini berada pada tahap {$stepNumber} ({$stepLabel}) dan sedang menunggu peninjauan dari {$reviewer->name}.";
            }

            if ($actor->role === 'supervisor') {
                return 'Anda bukan atasan langsung dari pemohon pengajuan ini.';
            }

            return 'Anda tidak memiliki kewenangan untuk meninjau pengajuan ini.';
        }

        return 'Status pengajuan tidak memungkinkan pengambilan keputusan saat ini.';
    }

    public function actionType(User $actor, Model $submission): ?string
    {
        if (! $this->canDecide($actor, $submission)) {
            return null;
        }

        if ($submission->status === 'cancellation_requested') {
            return 'cancellation';
        }

        return 'approval';
    }

    public function impactPreview(string $kind, Model $submission, string $decision, ?bool $requestCancellation = false): array
    {
        $status = $submission->status;
        $isPermission = ($kind === 'permission' || $submission instanceof Permission);
        $deductsBalance = $isPermission && (
            ($submission->leave_policy_snapshot['deducts_balance'] ?? false) ||
            ($submission->leaveType?->deducts_balance ?? false)
        );
        $durationDays = (int) ($submission->duration_days ?? 1);

        if ($status === 'cancellation_requested') {
            if ($decision === 'approved') {
                $impact = $deductsBalance
                    ? "Pengajuan menjadi dibatalkan; saldo cuti terpakai ({$durationDays} hari) dikembalikan ke saldo tersedia."
                    : "Pengajuan menjadi dibatalkan. Keputusan ini tidak memengaruhi saldo cuti.";
                return [
                    'decision_label' => 'Setujui Pembatalan',
                    'impact' => $impact,
                    'badge' => 'error',
                ];
            }

            return [
                'decision_label' => 'Tolak Pembatalan',
                'impact' => "Permohonan pembatalan ditolak. Pengajuan kembali disetujui dan tetap berlaku. Saldo cuti tetap terpakai.",
                'badge' => 'status-ontime',
            ];
        }

        if ($decision === 'approved') {
            $isFinal = true;
            if (! empty($submission->approval_steps)) {
                $isFinal = ((int) $submission->approval_step + 1 >= count($submission->approval_steps));
            }

            if ($isFinal) {
                $impact = $deductsBalance
                    ? "Pengajuan disetujui final; cadangan cuti ({$durationDays} hari) menjadi saldo terpakai."
                    : ($isPermission
                        ? "Pengajuan disetujui final. Keputusan ini tidak memengaruhi saldo cuti."
                        : "Pengajuan disetujui final.");
                return [
                    'decision_label' => 'Setujui Pengajuan (Final)',
                    'impact' => $impact,
                    'badge' => 'status-ontime',
                ];
            }

            $impact = $deductsBalance
                ? "Lanjut ke tahap peninjau berikutnya; cuti bersaldo tetap dicadangkan."
                : "Lanjut ke tahap peninjau berikutnya.";
            return [
                'decision_label' => 'Setujui Tahap Ini',
                'impact' => $impact,
                'badge' => 'status-ontime',
            ];
        }

        if ($decision === 'rejected') {
            $impact = $deductsBalance
                ? "Alur pengajuan berakhir (ditolak); cadangan cuti ({$durationDays} hari) dilepas kembali ke saldo tersedia."
                : ($isPermission
                    ? "Alur pengajuan berakhir (ditolak). Tidak ada mutasi saldo cuti."
                    : "Pengajuan ditolak.");
            return [
                'decision_label' => 'Tolak Pengajuan',
                'impact' => $impact,
                'badge' => 'error',
            ];
        }

        return [
            'decision_label' => ucfirst($decision),
            'impact' => 'Keputusan akan diproses secara atomik oleh sistem.',
            'badge' => 'muted',
        ];
    }

    public static function classifyEvent(object $event): array
    {
        $snapshot = is_array($event->snapshot) ? $event->snapshot : json_decode($event->snapshot ?? '{}', true);
        $from = $event->from_status ?? '';
        $to = $event->to_status ?? '';
        $beforeStep = (int) ($snapshot['before']['approval_step'] ?? 0);
        $afterStep = (int) ($snapshot['after']['approval_step'] ?? 0);
        $stageAdvanced = ($afterStep > $beforeStep);

        if ($from === 'cancellation_requested' && $to === 'approved') {
            return [
                'action' => 'cancellation_rejected',
                'event_type' => 'cancellation_rejected',
                'badge_class' => 'status-ontime',
                'badge_label' => 'Pembatalan ditolak',
                'action_label' => 'Pembatalan ditolak; pengajuan tetap disetujui',
                'subtext' => 'Permohonan pembatalan ditolak oleh pengelola. Pengajuan tetap disetujui.',
            ];
        }

        if ($from === 'cancellation_requested' && $to === 'cancelled') {
            return [
                'action' => 'cancellation_approved',
                'event_type' => 'cancellation_approved',
                'badge_class' => 'error',
                'badge_label' => 'Pembatalan disetujui',
                'action_label' => 'Pembatalan disetujui',
                'subtext' => 'Permohonan pembatalan disetujui oleh pengelola. Saldo cuti terpakai dikembalikan.',
            ];
        }

        if ($from === 'approved' && $to === 'cancellation_requested') {
            return [
                'action' => 'cancellation_requested',
                'event_type' => 'cancellation_requested',
                'badge_class' => 'amber',
                'badge_label' => 'Pembatalan diajukan',
                'action_label' => 'Pembatalan diajukan',
                'subtext' => 'Permohonan pembatalan diajukan oleh pemohon dan menunggu keputusan pengelola.',
            ];
        }

        if ($stageAdvanced) {
            return [
                'action' => 'approved',
                'event_type' => 'stage_approved',
                'badge_class' => 'status-ontime',
                'badge_label' => 'Tahap disetujui',
                'action_label' => 'Tahap disetujui; menunggu tahap berikutnya',
                'subtext' => 'Tahap ' . ($beforeStep + 1) . ' disetujui. Pengajuan diteruskan ke tahap berikutnya.',
            ];
        }

        if ($from === 'pending' && $to === 'approved') {
            return [
                'action' => 'approved',
                'event_type' => 'approved',
                'badge_class' => 'status-ontime',
                'badge_label' => 'Pengajuan disetujui',
                'action_label' => 'Pengajuan disetujui',
                'subtext' => 'Seluruh tahap persetujuan telah disetujui final.',
            ];
        }

        if ($to === 'rejected') {
            return [
                'action' => 'rejected',
                'event_type' => 'rejected',
                'badge_class' => 'error',
                'badge_label' => 'Pengajuan ditolak',
                'action_label' => 'Pengajuan ditolak',
                'subtext' => 'Pengajuan ditolak oleh peninjau.',
            ];
        }

        if ($to === 'cancelled') {
            return [
                'action' => 'cancelled',
                'event_type' => 'cancelled',
                'badge_class' => 'error',
                'badge_label' => 'Pengajuan dibatalkan',
                'action_label' => 'Pengajuan dibatalkan',
                'subtext' => 'Pengajuan dibatalkan oleh pemohon.',
            ];
        }

        return [
            'action' => 'updated',
            'event_type' => 'updated',
            'badge_class' => 'muted',
            'badge_label' => 'Informasi diperbarui',
            'action_label' => 'Informasi diperbarui',
            'subtext' => 'Data pengajuan telah diperbarui.',
        ];
    }

    private function locked(User $actor, string $kind, int $id): array
    {
        Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
        $actor = User::where('company_id', $actor->company_id)->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->isEmploymentActive(), 401);
        $class = self::model($kind);
        $row = $class::whereHas('user', fn ($q) => $q->where('company_id', $actor->company_id))->whereKey($id)->lockForUpdate()->firstOrFail();
        $owner = User::where('company_id', $actor->company_id)->whereKey($row->user_id)->firstOrFail();

        return [$actor, $row, $owner];
    }

    public function decide(User $actor, string $kind, int $id, string $status, int $version, string $reason, bool $requestCancellation = false): Model
    {
        return DB::transaction(function () use ($actor, $kind, $id, $status, $version, $reason, $requestCancellation) {
            [$actor, $row, $owner] = $this->locked($actor, $kind, $id);
            abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 1000, 422, 'Alasan keputusan wajib diisi maksimal 1.000 karakter.');

            $before = $row->status;
            $beforeSnapshot = $this->snapshot($row);

            // Case A: Owner requests cancellation
            if ($status === 'cancelled') {
                abort_unless((int) $owner->id === (int) $actor->id, 403, 'Hanya pemilik yang dapat membatalkan pengajuannya.');

                if ($row->status === 'approved') {
                    abort_unless($kind === 'permission' && $requestCancellation, 409, 'Pengajuan berstatus final tidak dapat dibatalkan langsung.');
                    abort_unless((int) $row->approval_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum mengambil keputusan.');

                    // Approved leave cancellation must go through HR review
                    $row->forceFill([
                        'status' => 'cancellation_requested',
                        'approval_version' => $version + 1,
                        'cancellation_reason' => trim($reason),
                        'cancellation_requested_at' => now(),
                    ]);
                    $row->save();
                    $this->event($actor, $kind, $row, $before, 'Permintaan pembatalan oleh pemohon: '.trim($reason), $beforeSnapshot);

                    return $row;
                }

                abort_unless($row->status === 'pending', 409, 'Pengajuan berstatus final tidak dapat dibatalkan langsung.');
                abort_unless((int) $row->approval_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum mengambil keputusan.');

                $row->forceFill([
                    'status' => 'cancelled',
                    'approval_version' => $version + 1,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'decision_reason' => trim($reason),
                ]);
                if ($kind === 'permission') {
                    $row->is_approved = false;
                    app(LeaveBalanceService::class)->releaseReserved($row, $actor, 'Pengembalian cadangan: dibatalkan pemohon. Alasan: '.trim($reason));
                }
                $row->save();
                $this->event($actor, $kind, $row, $before, trim($reason), $beforeSnapshot);

                return $row;
            }

            // Case B: Reviewing a cancellation request
            if ($row->status === 'cancellation_requested') {
                abort_unless((int) $owner->id !== (int) $actor->id, 403, 'Persetujuan pembatalan harus ditinjau oleh pengelola lain.');
                abort_unless(in_array($actor->role, ['admin', 'super admin', 'hrd'], true) || ($actor->role === 'supervisor' && (int) $owner->manager_id === (int) $actor->id), 403, 'Hanya HR/Atasan yang dapat meninjau permintaan pembatalan.');
                abort_unless((int) $row->approval_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum mengambil keputusan.');

                if ($status === 'approved') {
                    // HR approves the cancellation -> status becomes cancelled
                    $row->forceFill([
                        'status' => 'cancelled',
                        'approval_version' => $version + 1,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'decision_reason' => trim($reason),
                    ]);
                    if ($kind === 'permission') {
                        $row->is_approved = false;
                        app(LeaveBalanceService::class)->restoreUsed($row, $actor, 'Pengembalian saldo: pembatalan cuti disetujui HR. Alasan: '.trim($reason));
                    }
                    $row->save();
                    $this->event($actor, $kind, $row, $before, 'Pembatalan disetujui HR: '.trim($reason), $beforeSnapshot);

                    return $row;
                } elseif ($status === 'rejected') {
                    // HR rejects the cancellation -> status reverts to approved
                    $row->forceFill([
                        'status' => 'approved',
                        'approval_version' => $version + 1,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'decision_reason' => trim($reason),
                    ]);
                    if ($kind === 'permission') {
                        $row->is_approved = true;
                    }
                    $row->save();
                    $this->event($actor, $kind, $row, $before, 'Permintaan pembatalan ditolak HR: '.trim($reason), $beforeSnapshot);

                    return $row;
                }
                abort(422);
            }

            // Case C: Standard approval / rejection for pending or needs_review
            abort_unless(in_array($status, ['approved', 'rejected'], true), 422);
            abort_unless((int) $owner->id !== (int) $actor->id, 403, 'Pengajuan sendiri harus ditinjau pengelola lain.');

            if ($row->approval_steps) {
                abort_unless((int) app(ApprovalWorkflow::class)->reviewer($row)?->id === (int) $actor->id, 403, 'Hanya peninjau tahap aktif yang dapat memutuskan.');
            } else {
                abort_unless(in_array($actor->role, ['admin', 'super admin', 'hrd'], true) || ($actor->role === 'supervisor' && (int) $owner->manager_id === (int) $actor->id), 403, 'Anda tidak berwenang meninjau pengajuan ini.');
            }

            if ($row->status === 'needs_review') {
                abort(409, 'Pengajuan legacy bertanda "Perlu ditinjau" tidak dapat diputuskan melalui alur approval biasa; harus melalui proses rekonsiliasi data.');
            }

            abort_unless((int) $row->approval_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum mengambil keputusan.');
            abort_unless($row->status === 'pending', 409, 'Keputusan final tidak dapat diubah.');

            $isFinalApproval = false;
            if ($status === 'approved') {
                if ($row->approval_steps) {
                    $next = (int) $row->approval_step + 1;
                    $row->approval_step = $next;
                    if ($next < count($row->approval_steps)) {
                        $status = 'pending';
                    } else {
                        $isFinalApproval = true;
                    }
                } else {
                    $isFinalApproval = true;
                }
            }

            $row->forceFill([
                'status' => $status,
                'approval_version' => $version + 1,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'decision_reason' => trim($reason),
            ]);

            if ($kind === 'permission') {
                $row->is_approved = ($status === 'approved');

                if ($isFinalApproval) {
                    app(LeaveBalanceService::class)->useReserved($row, $actor, 'Pemakaian saldo: disetujui final. Alasan: '.trim($reason));
                } elseif ($status === 'rejected') {
                    app(LeaveBalanceService::class)->releaseReserved($row, $actor, 'Pengembalian cadangan: pengajuan ditolak. Alasan: '.trim($reason));
                }
            }

            $row->save();
            $this->event($actor, $kind, $row, $before, trim($reason), $beforeSnapshot);

            return $row;
        }, 3);
    }

    public function edit(User $actor, string $kind, int $id, int $version, callable $save): Model
    {
        return DB::transaction(function () use ($actor, $kind, $id, $version, $save) {
            [$actor,$row,$owner] = $this->locked($actor, $kind, $id);
            abort_unless(in_array($actor->role, ['admin', 'super admin', 'hrd'], true) || ($actor->role === 'supervisor' && ((int) $owner->id === (int) $actor->id || (int) $owner->manager_id === (int) $actor->id)), 403);
            abort_unless((int) $row->approval_step === 0 && $row->status === 'pending' && (int) $row->approval_version === $version, 409, 'Pengajuan berubah atau sudah final. Muat ulang halaman.');
            $beforeSnapshot = $this->snapshot($row);
            $row = $save($row);
            $row->forceFill(['approval_version' => $version + 1])->save();
            $this->event($actor, $kind, $row, 'pending', 'Informasi pengajuan diperbarui', $beforeSnapshot);

            return $row;
        }, 3);
    }

    private function snapshot(Model $row): array
    {
        return array_intersect_key($row->getAttributes(), array_flip([
            'user_id', 'date', 'date_permission', 'start_date', 'end_date', 'duration_days', 'dates_breakdown',
            'reason', 'duration', 'description', 'amount', 'status', 'approval_legacy', 'approval_version',
            'approval_steps', 'approval_step', 'leave_type_id', 'leave_policy_snapshot', 'cancellation_reason', 'cancellation_requested_at',
        ]));
    }

    private function event(User $actor, string $kind, Model $row, string $before, string $reason, array $beforeSnapshot): void
    {
        $snapshot = ['before' => $beforeSnapshot, 'after' => $this->snapshot($row)];
        DB::table('submission_approval_events')->insert(['company_id' => $actor->company_id, 'kind' => $kind, 'submission_id' => $row->id, 'actor_id' => $actor->id, 'version' => $row->approval_version, 'from_status' => $before, 'to_status' => $row->status, 'reason' => $reason, 'snapshot' => json_encode($snapshot), 'created_at' => now()]);
    }
}
