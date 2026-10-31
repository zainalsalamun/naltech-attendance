<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Overtime;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    // Index (Get user's overtimes with pagination)
    public function index(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        $rows = Overtime::with('reviewer:id,name')->where('user_id', $request->user()->id)
            ->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Success',
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ], 200);
    }

    // Store (Create overtime request)
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isEmploymentActive(), 403, 'Pengajuan hanya dapat dibuat untuk karyawan aktif.');

        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            // Batas teknis yang diusulkan: maksimal 1.440 menit (24 jam) per pengajuan tanggal kerja.
            'duration' => 'required|integer|min:1|max:1440',
            'description' => 'nullable|string|max:10000',
        ]);

        $overtime = Overtime::create([
            'user_id' => $actor->id,
            'date' => $request->date,
            'duration' => $request->duration,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response([
            'message' => 'Overtime request created',
            'data' => $overtime->refresh()->load('reviewer:id,name'),
        ], 201);
    }

    // Show
    public function show(Request $request, $id)
    {
        $query = Overtime::with('reviewer:id,name');
        if (! in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true)) {
            $query->where('user_id', $request->user()->id);
        }
        $overtime = $query->findOrFail($id);
        app(\App\Services\SubmissionApproval::class)->detail('overtime', $overtime);

        return response([
            'message' => 'Success',
            'data' => $overtime,
        ], 200);
    }
}
