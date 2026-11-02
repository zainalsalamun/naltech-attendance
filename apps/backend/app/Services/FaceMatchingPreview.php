<?php

namespace App\Services;

use App\Models\FaceEnrollment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FaceMatchingPreview
{
    public function compare(User $user, UploadedFile $photo): array
    {
        abort_unless(config('face.preview_enabled') && config('face.provider') === 'self_hosted'
            && filled(config('face.self_hosted_key')), 503, 'Uji pencocokan server belum tersedia.');
        $reference = FaceEnrollment::where('user_id', $user->id)->where('company_id', $user->company_id)
            ->where('status', 'approved')->first();
        abort_unless($reference?->photo_path && Storage::disk('face_private')->exists($reference->photo_path),
            409, 'Foto referensi belum disetujui atau tidak tersedia. Hubungi HR/admin.');
        try {
            $referenceBytes = Storage::disk('face_private')->get($reference->photo_path);
        } catch (\Throwable) {
            abort(409, 'Foto referensi tidak tersedia. Hubungi HR/admin.');
        }
        abort_unless(is_string($referenceBytes) && strlen($referenceBytes) > 0 && strlen($referenceBytes) <= 2 * 1024 * 1024,
            409, 'Foto referensi tidak valid. Hubungi HR/admin.');
        try {
            $response = Http::withToken(config('face.self_hosted_key'))->withoutRedirecting()
                ->connectTimeout(2)->timeout(12)->post(rtrim(config('face.self_hosted_url'), '/').'/v1/compare', [
                    'reference' => base64_encode($referenceBytes),
                    'probe' => base64_encode($photo->get()),
                ]);
        } catch (ConnectionException) {
            abort(503, 'Server pencocokan belum dapat dihubungi. Coba lagi.');
        }
        if ($response->status() === 422) {
            abort(422, 'Gunakan foto dengan satu wajah yang terlihat jelas.');
        }
        abort_unless($response->successful(), 503, 'Server pencocokan belum dapat memproses foto.');
        $data = $response->json();
        $score = is_array($data) ? ($data['similarity'] ?? null) : null;
        abort_unless(is_array($data) && ($data['engine'] ?? null) === 'opencv-sface'
            && (is_int($score) || is_float($score)) && is_finite((float) $score) && $score >= -1 && $score <= 1
            && ($data['reference_faces'] ?? null) === 1 && ($data['probe_faces'] ?? null) === 1
            && ($data['liveness'] ?? null) === 'not_configured'
            && ($data['attendance_eligible'] ?? null) === false,
            503, 'Respons server pencocokan belum dapat diverifikasi.');
        abort_unless($reference->fresh()?->status === 'approved', 409, 'Foto referensi telah berubah. Muat ulang status wajah.');

        return [
            'matched' => $score >= (float) config('face.preview_similarity_threshold'),
            'liveness' => 'not_configured', 'attendance_eligible' => false,
            'message' => 'Hasil uji pencocokan saja. Ini belum verifikasi liveness atau absensi.',
        ];
    }
}
