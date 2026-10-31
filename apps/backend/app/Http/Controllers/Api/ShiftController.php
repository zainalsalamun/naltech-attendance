<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $shifts = Shift::all();

        return response()->json([
            'status' => 'success',
            'data' => $shifts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd', 'supervisor'], true), 403);
        //
        $shift = Shift::create(\App\Support\ShiftInput::validate($request));

        return response()->json([
            'status' => 'success',
            'data' => $shift,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $shift = Shift::find($id);
        if ($shift) {
            return response()->json([
                'status' => 'success',
                'data' => $shift,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Shift not found',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd', 'supervisor'], true), 403);
        //
        $shift = Shift::find($id);
        if ($shift) {
            $shift->update(\App\Support\ShiftInput::validate($request));

            return response()->json([
                'status' => 'success',
                'data' => $shift,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Shift not found',
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
