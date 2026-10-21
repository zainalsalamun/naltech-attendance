<?php

namespace App\Console\Commands;

use App\Models\FaceEnrollment;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeFacePhotos extends Command
{
    protected $signature = 'face:purge-photos';

    protected $description = 'Expire old pending enrollments and remove inactive/orphaned private face photos';

    public function handle(FaceEnrollmentService $service): int
    {
        FaceEnrollment::where('status', 'pending')->where('created_at', '<', now()->subDays(config('face.pending_retention_days')))
            ->eachById(function ($candidate) {
                DB::transaction(function () use ($candidate) {
                    User::whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
                    $row = FaceEnrollment::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if ($row->status !== 'pending') {
                        return;
                    }
                    $row->update(['status' => 'expired', 'reviewed_at' => now(), 'review_reason' => 'Masa peninjauan pendaftaran berakhir.']);
                    DB::table('face_enrollment_events')->insert([
                        'company_id' => $row->company_id, 'face_enrollment_id' => $row->id,
                        'action' => 'expired', 'reason' => 'Masa peninjauan pendaftaran berakhir.', 'created_at' => now(),
                    ]);
                });
            });
        FaceEnrollment::whereNotIn('status', ['pending', 'approved'])->whereNotNull('photo_path')
            ->eachById(fn ($row) => $service->purgeInactivePhotos($row->company_id, $row->user_id));
        $disk = Storage::disk('face_private');
        // A grace period avoids deleting files whose upload transaction is in flight.
        foreach ($disk->allFiles() as $path) {
            if ($disk->lastModified($path) < now()->subDay()->timestamp
                && ! FaceEnrollment::where('photo_path', $path)->exists()) {
                $disk->delete($path);
            }
        }
        $this->info('Private face photo maintenance completed. No biometric data printed.');

        return self::SUCCESS;
    }
}
