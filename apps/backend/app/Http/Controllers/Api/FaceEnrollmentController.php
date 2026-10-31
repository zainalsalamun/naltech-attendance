<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEnrollment;
use App\Services\FaceEnrollmentService;
use Illuminate\Http\Request;

class FaceEnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $rows = FaceEnrollment::where('user_id', $request->user()->id)->orderByDesc('id')->limit(10)->get();
        $active = FaceEnrollment::where('user_id', $request->user()->id)->whereIn('status', ['pending', 'approved'])->get();
        $rows = $rows->merge($active)->unique('id')->sortByDesc('id')->values();

        return response()->json(['data' => $rows->map->summary(), 'matching_preview_available' => config('face.preview_enabled') && config('face.provider') === 'self_hosted' && filled(config('face.self_hosted_key')),
            'attendance_face_available' => false])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, FaceEnrollmentService $service)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048', 'dimensions:min_width=320,min_height=320,max_width=4096,max_height=4096'],
            'consent' => ['required', 'accepted'],
        ]);
        $enrollment = $service->submit($request->user(), $request->file('photo'));

        return response()->json(['message' => 'Foto diajukan untuk ditinjau HR/admin.', 'data' => $enrollment->summary()], 201);
    }

    public function destroy(Request $request, FaceEnrollment $enrollment, FaceEnrollmentService $service)
    {
        $service->withdraw($request->user(), $enrollment);

        return response()->json(['message' => 'Pendaftaran wajah dicabut.']);
    }

    public function preview(Request $request, \App\Services\FaceMatchingPreview $matching)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048', 'dimensions:min_width=320,min_height=320,max_width=4096,max_height=4096'],
        ]);

        return response()->json(['data' => $matching->compare($request->user(), $request->file('photo'))])
            ->header('Cache-Control', 'no-store');
    }
}
