<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Overtime;
use App\Models\User;

class OvertimeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Overtime::with('user');

        if (!in_array($user->role, ['admin', 'super admin', 'hrd', 'supervisor'])) {
            $query->where('user_id', $user->id);
        }

        if ($request->name) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        $overtimes = $query->orderBy('date', 'desc')->paginate(5);
        return view('pages.overtime.index', compact('overtimes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::all();
        return view('pages.overtime.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        Overtime::create($request->all());

        return redirect()->route('overtimes.index')->with('success', 'Overtime request created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $overtime = Overtime::with('user')->findOrFail($id);
        return view('pages.overtime.show', compact('overtime'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $overtime = Overtime::findOrFail($id);
        $users = User::all();
        return view('pages.overtime.edit', compact('overtime', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $overtime = Overtime::findOrFail($id);
        $overtime->update($request->all());

        return redirect()->route('overtimes.index')->with('success', 'Overtime updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $overtime = Overtime::findOrFail($id);
        $overtime->delete();

        return redirect()->route('overtimes.index')->with('success', 'Overtime deleted successfully.');
    }
}
