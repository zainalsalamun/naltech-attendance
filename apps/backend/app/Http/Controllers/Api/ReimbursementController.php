<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use Illuminate\Http\Request;

class ReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        $rows = Reimbursement::with('reviewer:id,name')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20);

        return response()->json(['data' => $rows->items(), 'meta' => [
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
        ]]);
    }

    public function show(Request $request, $id)
    {
        $row = Reimbursement::with('reviewer:id,name')->where('user_id', $request->user()->id)->findOrFail($id);
        app(\App\Services\SubmissionApproval::class)->detail('reimbursement', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isEmploymentActive(), 403, 'Pengajuan hanya dapat dibuat untuk karyawan aktif.');

        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'description' => 'required|string|max:5000',
            'amount' => 'required|numeric|gt:0|max:9999999999.99|decimal:0,2',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $row = app(\App\Services\SubmissionAttachment::class)->save(new Reimbursement, [
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'status' => 'pending',
        ], $request->file('image'), 'reimbursements');

        return response()->json(['message' => 'Pengajuan berhasil dikirim.', 'data' => $row->refresh()->load('reviewer:id,name')], 201);
    }
}
