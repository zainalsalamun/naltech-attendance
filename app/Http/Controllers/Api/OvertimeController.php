<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Overtime;

class OvertimeController extends Controller
{
    // Index (Get user's overtimes)
    public function index(Request $request)
    {
        $overtimes = Overtime::where('user_id', $request->user()->id)
            ->orderBy('date', 'desc')
            ->get();

        return response([
            'message' => 'Success',
            'data' => $overtimes
        ], 200);
    }

    // Store (Create overtime request)
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $overtime = Overtime::create([
            'user_id' => $request->user()->id,
            'date' => $request->date,
            'duration' => $request->duration,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response([
            'message' => 'Overtime request created',
            'data' => $overtime
        ], 201);
    }

    // Show
    public function show($id)
    {
        $overtime = Overtime::findOrFail($id);
        return response([
            'message' => 'Success',
            'data' => $overtime
        ], 200);
    }
}
