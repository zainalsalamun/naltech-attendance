<?php

namespace App\Services;

use App\Models\FaceEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FaceEnrollmentService
{
    public function submit(User $actor, UploadedFile $photo): FaceEnrollment
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $photo, &$path) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_if(FaceEnrollment::where('user_id', $actor->id)->where('status', 'pending')->exists(),
                    409, 'Pendaftaran wajah masih menunggu persetujuan.');
                $path = $photo->store($actor->company_id.'/'.$actor->id, 'face_private');
                abort_unless(is_string($path) && $path !== '', 503, 'Foto belum dapat disimpan. Coba lagi.');

                $enrollment = FaceEnrollment::create([
                    'company_id' => $actor->company_id, 'user_id' => $actor->id,
                    'photo_path' => $path, 'status' => 'pending', 'consented_at' => now(),
                ]);
                $this->event($enrollment, $actor, 'submitted', null);

                return $enrollment;
            });
        } catch (\Throwable $exception) {
            if (is_string($path) && $path !== '') {
                Storage::disk('face_private')->delete($path);
            }
            throw $exception;
        }
    }

    public function review(User $actor, FaceEnrollment $enrollment, string $decision, ?string $reason): void
    {
        abort_unless(in_array($actor->role, ['admin', 'super admin', 'hrd'], true), 403);
        abort_unless((int) $enrollment->company_id === (int) $actor->company_id, 404);
        abort_if((int) $enrollment->user_id === (int) $actor->id, 403, 'Anda tidak dapat meninjau pendaftaran wajah sendiri.');
        abort_unless(in_array($decision, ['approved', 'rejected', 'revoked'], true), 422);
        DB::transaction(function () use ($actor, $enrollment, $decision, $reason) {
            // Same lock order as submission/withdrawal to serialize replacements.
            User::whereKey($enrollment->user_id)->where('company_id', $actor->company_id)->lockForUpdate()->firstOrFail();
            $row = FaceEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === ($decision === 'revoked' ? 'approved' : 'pending'),
                409, 'Status pendaftaran sudah berubah. Muat ulang halaman.');
            if ($decision === 'approved') {
                abort_unless($row->photo_path && Storage::disk('face_private')->exists($row->photo_path),
                    409, 'Foto referensi tidak tersedia. Minta pendaftaran ulang.');
                foreach (FaceEnrollment::where('user_id', $row->user_id)->where('status', 'approved')->get() as $previous) {
                    $previous->update(['status' => 'superseded']);
                    $this->event($previous, $actor, 'superseded', 'Digantikan oleh pendaftaran yang disetujui.');
                }
            }
            $row->update(['status' => $decision, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_reason' => $reason]);
            $this->event($row, $actor, $decision, $reason);
        });
        $this->purgeInactivePhotos($actor->company_id, $enrollment->user_id);
    }

    public function withdraw(User $actor, FaceEnrollment $enrollment): void
    {
        abort_unless((int) $enrollment->user_id === (int) $actor->id
            && (int) $enrollment->company_id === (int) $actor->company_id, 404);
        DB::transaction(function () use ($actor, $enrollment) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $row = FaceEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['pending', 'approved'], true), 409, 'Pendaftaran sudah tidak aktif.');
            $row->update(['status' => 'revoked', 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_reason' => 'Dicabut oleh pemilik akun.']);
            $this->event($row, $actor, 'revoked', 'Dicabut oleh pemilik akun.');
        });
        $this->purgeInactivePhotos($actor->company_id, $actor->id);
    }

    public function purgeInactivePhotos(int $companyId, int $userId): void
    {
        FaceEnrollment::where('company_id', $companyId)->where('user_id', $userId)
            ->whereNotIn('status', ['pending', 'approved'])->whereNotNull('photo_path')
            ->each(function ($row) {
                if (Storage::disk('face_private')->delete($row->photo_path)) {
                    $row->update(['photo_path' => null]);
                }
            });
    }

    private function event(FaceEnrollment $row, User $actor, string $action, ?string $reason): void
    {
        DB::table('face_enrollment_events')->insert([
            'company_id' => $row->company_id, 'face_enrollment_id' => $row->id,
            'actor_id' => $actor->id, 'action' => $action, 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
