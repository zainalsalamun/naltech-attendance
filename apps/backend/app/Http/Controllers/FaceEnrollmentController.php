<?php

namespace App\Http\Controllers;

use App\Models\FaceEnrollment;
use App\Services\FaceEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FaceEnrollmentController extends Controller
{
    private function authorizeReviewer(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeReviewer($request);
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'revoked', 'superseded', 'expired'])]]);
        $status = $data['status'] ?? 'pending';
        $enrollments = FaceEnrollment::with(['user', 'reviewer'])->where('status', $status)->orderByDesc('id')->paginate(15)->withQueryString();

        return response()->view('pages.face-enrollments.index', compact('enrollments', 'status'))
            ->header('Cache-Control', 'no-store');
    }

    public function photo(Request $request, FaceEnrollment $enrollment)
    {
        $this->authorizeReviewer($request);
        abort_unless((int) $enrollment->company_id === (int) $request->user()->company_id, 404);
        abort_unless(in_array($enrollment->status, ['pending', 'approved'], true) && $enrollment->photo_path, 404);
        $disk = Storage::disk('face_private');
        abort_unless($disk->exists($enrollment->photo_path), 404);

        return $disk->response($enrollment->photo_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function update(Request $request, FaceEnrollment $enrollment, FaceEnrollmentService $service)
    {
        $this->authorizeReviewer($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'revoked'])],
            'identity_confirmed' => ['exclude_unless:decision,approved', 'required', 'accepted'],
            'reason' => ['required_unless:decision,approved', 'nullable', 'string', 'max:500'],
        ]);
        $service->review($request->user(), $enrollment, $data['decision'], $data['reason'] ?? null);

        return redirect()->route('face-enrollments.index')->with('success', 'Peninjauan pendaftaran wajah disimpan.');
    }
}
