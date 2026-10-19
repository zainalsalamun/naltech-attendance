<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;

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
            'data' => $shifts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required',
            'time_in' => 'required',
            'time_out' => 'required',
        ]);

        $shift = Shift::create([
            'name' => $request->name,
            'time_in' => $request->time_in,
            'time_out' => $request->time_out,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $shift
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
                'data' => $shift
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Shift not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $shift = Shift::find($id);
        if ($shift) {
            $shift->update($request->all());
            return response()->json([
                'status' => 'success',
                'data' => $shift
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Shift not found'
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
