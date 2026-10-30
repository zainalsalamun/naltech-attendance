<?php

namespace App\Http\Controllers;

use App\Services\SubmissionApproval;
use Illuminate\Http\Request;

class SubmissionApprovalController extends Controller
{
    public function decision(Request $request, string $kind, int $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,cancelled'],
            'approval_version' => ['required', 'integer', 'min:0'],
            'decision_reason' => ['required', 'string', 'max:1000'],
            'request_cancellation' => ['nullable', 'boolean'],
        ]);
        $row = app(SubmissionApproval::class)->decide(
            $request->user(),
            $kind,
            $id,
            $data['status'],
            $data['approval_version'],
            $data['decision_reason'],
            $request->boolean('request_cancellation')
        );
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Keputusan tersimpan.', 'data' => $row->load('reviewer:id,name')]);
        }

        return back()->with('success', 'Keputusan tersimpan. Riwayat pengajuan dipertahankan.');
    }

    public function cancel(Request $request, string $kind, int $id)
    {
        $request->merge(['status' => 'cancelled']);

        return $this->decision($request, $kind, $id);
    }
}
