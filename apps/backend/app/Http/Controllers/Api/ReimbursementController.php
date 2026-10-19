<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reimbursement;

class ReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $reimbursements = Reimbursement::where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $reimbursements
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $image = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('assets/reimbursements', 'public');
        }

        $reimbursement = Reimbursement::create([
            'user_id' => $request->user()->id,
            'date' => $request->date,
            'description' => $request->description,
            'amount' => $request->amount,
            'image' => $image,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reimbursement created successfully',
            'data' => $reimbursement
        ], 201);
    }
}
