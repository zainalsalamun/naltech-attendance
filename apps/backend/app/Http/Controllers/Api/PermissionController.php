<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        $rows = Permission::with('reviewer:id,name')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20);

        return response()->json(['data' => $rows->items(), 'meta' => [
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
        ]]);
    }

    public function show(Request $request, $id)
    {
        $row = Permission::with('reviewer:id,name')->where('user_id', $request->user()->id)->findOrFail($id);
        app(\App\Services\SubmissionApproval::class)->detail('permission', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'leave_type_id' => 'nullable|integer|min:1',
            'reason' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (empty($data['start_date']) && ! empty($data['date'])) {
            $data['start_date'] = $data['date'];
            $data['end_date'] = $data['date'];
        } elseif (! empty($data['start_date']) && empty($data['end_date'])) {
            $data['end_date'] = $data['start_date'];
        }

        if (empty($data['start_date'])) {
            return response()->json([
                'message' => 'Tanggal pengajuan cuti/izin wajib diisi.',
                'errors' => ['start_date' => ['Tanggal awal pengajuan cuti wajib diisi.']],
            ], 422);
        }

        $row = app(\App\Services\LeavePolicy::class)->submit($request->user(), $data, $request->file('image'));

        return response()->json(['message' => 'Pengajuan berhasil dikirim.', 'data' => $row->refresh()->load('reviewer:id,name')], 201);
    }
}
