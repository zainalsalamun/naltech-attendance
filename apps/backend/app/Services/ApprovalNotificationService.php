<?php

namespace App\Services;

use App\Mail\ApprovalStatusNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class ApprovalNotificationService
{
    public function __construct(
        private readonly ApprovalWorkflow $workflow,
    ) {}

    public function notifySubmitted(string $kind, Model $row): void
    {
        if ($row->status !== 'pending') {
            return;
        }

        $owner = $this->resolveOwner($row);
        $reviewer = $this->workflow->reviewer($row);
        if (! $owner || ! $reviewer || (int) $reviewer->id === (int) $owner->id) {
            return;
        }

        $kindLabel = $this->kindLabel($kind);
        $title = "Pengajuan {$kindLabel} Baru Menunggu Tinjauan";
        $summary = "{$owner->name} mengajukan {$kindLabel} dan membutuhkan keputusan Anda.";

        $this->dispatchToUser($reviewer, $kind, $row, $title, $summary, $owner->name, 'Menunggu persetujuan', null);
    }

    public function notifyStageAdvanced(string $kind, Model $row, User $actor): void
    {
        $owner = $this->resolveOwner($row);
        $nextReviewer = $this->workflow->reviewer($row);
        $kindLabel = $this->kindLabel($kind);
        $stepInfo = $row->approval_progress;
        $stageLabel = $stepInfo['label'] ?? 'Tahap berikutnya';

        if ($nextReviewer && (! $owner || (int) $nextReviewer->id !== (int) $owner->id)) {
            $title = "Pengajuan {$kindLabel} Menunggu Tinjauan ({$stageLabel})";
            $summary = "Pengajuan {$kindLabel} dari ".($owner?->name ?? 'karyawan')." telah disetujui {$actor->name} dan kini masuk ke tahap Anda ({$stageLabel}).";
            $this->dispatchToUser($nextReviewer, $kind, $row, $title, $summary, $owner?->name ?? 'Karyawan', "Menunggu {$stageLabel}", $row->decision_reason);
        }

        if ($owner) {
            $title = "Progres Pengajuan {$kindLabel}";
            $summary = "Pengajuan {$kindLabel} Anda disetujui oleh {$actor->name} dan diteruskan ke {$stageLabel}.";
            $this->dispatchToUser($owner, $kind, $row, $title, $summary, $owner->name, "Diteruskan ke {$stageLabel}", $row->decision_reason);
        }
    }

    public function notifyDecided(string $kind, Model $row, string $outcome, string $reason): void
    {
        $owner = $this->resolveOwner($row);
        if (! $owner) {
            return;
        }

        $kindLabel = $this->kindLabel($kind);
        $isApproved = $outcome === 'approved';
        $statusLabel = $isApproved ? 'Disetujui' : 'Ditolak';
        $title = "Pengajuan {$kindLabel} {$statusLabel}";
        $summary = $isApproved
            ? "Pengajuan {$kindLabel} Anda telah disetujui secara final."
            : "Pengajuan {$kindLabel} Anda ditolak oleh peninjau.";

        $this->dispatchToUser($owner, $kind, $row, $title, $summary, $owner->name, $statusLabel, $reason);
    }

    public function notifyCancellationRequested(string $kind, Model $row, string $reason): void
    {
        $owner = $this->resolveOwner($row);
        if (! $owner) {
            return;
        }

        $kindLabel = $this->kindLabel($kind);
        $title = "Permintaan Pembatalan {$kindLabel}";
        $summary = "{$owner->name} mengajukan pembatalan {$kindLabel} yang sebelumnya sudah disetujui dan menunggu keputusan HR/Admin.";

        $reviewers = User::query()
            ->where('company_id', $owner->company_id)
            ->whereIn('role', ['admin', 'super admin', 'hrd'])
            ->where('id', '!=', $owner->id)
            ->get()
            ->filter(fn (User $u) => $u->isEmploymentActive());

        foreach ($reviewers as $reviewer) {
            $this->dispatchToUser($reviewer, $kind, $row, $title, $summary, $owner->name, 'Menunggu pembatalan', $reason);
        }
    }

    public function notifyCancellationDecided(string $kind, Model $row, string $outcome, string $reason): void
    {
        $owner = $this->resolveOwner($row);
        if (! $owner) {
            return;
        }

        $kindLabel = $this->kindLabel($kind);
        $isCancelled = $outcome === 'cancelled';
        $statusLabel = $isCancelled ? 'Pembatalan Disetujui' : 'Pembatalan Ditolak (Tetap Disetujui)';
        $title = $isCancelled ? "Pembatalan {$kindLabel} Disetujui" : "Permintaan Pembatalan {$kindLabel} Ditolak";
        $summary = $isCancelled
            ? "Permintaan pembatalan {$kindLabel} Anda telah disetujui HR. Saldo cuti (jika ada) telah dikembalikan."
            : "Permintaan pembatalan {$kindLabel} Anda ditolak HR. Pengajuan awal tetap berlaku.";

        $this->dispatchToUser($owner, $kind, $row, $title, $summary, $owner->name, $statusLabel, $reason);
    }

    private function dispatchToUser(
        User $recipient,
        string $kind,
        Model $row,
        string $title,
        string $summary,
        string $applicantName,
        string $statusLabel,
        ?string $reason,
    ): void {
        if (! $recipient->isEmploymentActive()) {
            return;
        }

        $kindLabel = $this->kindLabel($kind);
        $actionUrl = $this->actionUrl($kind, $row);

        if (is_string($recipient->email) && trim($recipient->email) !== '') {
            try {
                Mail::to($recipient->email)->send(new ApprovalStatusNotification(
                    recipientName: $recipient->name,
                    notificationTitle: $title,
                    summaryLine: $summary,
                    kindLabel: $kindLabel,
                    applicantName: $applicantName,
                    statusLabel: $statusLabel,
                    reasonText: $reason,
                    actionUrl: $actionUrl,
                ));
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim email notifikasi approval.', [
                    'recipient_id' => $recipient->id,
                    'kind' => $kind,
                    'submission_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (is_string($recipient->fcm_token) && trim($recipient->fcm_token) !== '') {
            $this->sendFcm($recipient, $title, $summary, [
                'type' => 'submission_approval',
                'kind' => $kind,
                'submission_id' => (string) $row->id,
                'status' => (string) $row->status,
            ]);
        }
    }

    private function sendFcm(User $recipient, string $title, string $body, array $data): void
    {
        $messaging = $this->resolveMessaging();
        if (! $messaging) {
            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $recipient->fcm_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($data);
            $messaging->send($message);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim push notification FCM approval.', [
                'recipient_id' => $recipient->id,
                'kind' => $data['kind'] ?? null,
                'submission_id' => $data['submission_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveMessaging(): ?Messaging
    {
        if (! app()->bound(Messaging::class)) {
            $credentials = config('firebase.projects.app.credentials');
            if (empty($credentials) || (is_string($credentials) && ! file_exists($credentials))) {
                return null;
            }
        }

        try {
            return app(Messaging::class);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveOwner(Model $row): ?User
    {
        if ($row->relationLoaded('user') && $row->user instanceof User) {
            return $row->user;
        }

        return $row->user_id ? User::find($row->user_id) : null;
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'permission' => 'Cuti / Izin',
            'overtime' => 'Lembur',
            'reimbursement' => 'Reimbursement',
            default => 'Pengajuan',
        };
    }

    private function actionUrl(string $kind, Model $row): ?string
    {
        try {
            return match ($kind) {
                'permission' => route('permissions.show', $row),
                'overtime' => route('overtimes.edit', $row),
                'reimbursement' => route('reimbursements.edit', $row),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
