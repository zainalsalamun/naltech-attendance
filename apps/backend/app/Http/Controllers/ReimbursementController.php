<?php

namespace App\Http\Controllers;

use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Http\Request;

class ReimbursementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $reimbursements = Reimbursement::with('user')
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
        return view('pages.reimbursements.index', compact('reimbursements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('pages.reimbursements.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $image = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('assets/reimbursements', 'public');
        }

        Reimbursement::create([
            'user_id' => $request->user_id,
            'date' => $request->date,
            'description' => $request->description,
            'amount' => $request->amount,
            // 'status' => 'pending', // Default is pending
            'image' => $image,
        ]);

        return redirect()->route('reimbursements.index')->with('success', 'Reimbursement created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Reimbursement $reimbursement)
    {
        return view('pages.reimbursements.show', compact('reimbursement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Reimbursement $reimbursement)
    {
        $users = User::orderBy('name')->get();
        return view('pages.reimbursements.edit', compact('reimbursement', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reimbursement $reimbursement)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $image = $reimbursement->image;
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('assets/reimbursements', 'public');
        }

        $reimbursement->update([
            'user_id' => $request->user_id,
            'date' => $request->date,
            'description' => $request->description,
            'amount' => $request->amount,
            'status' => $request->status,
            'image' => $image,
        ]);

        return redirect()->route('reimbursements.index')->with('success', 'Reimbursement updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reimbursement $reimbursement)
    {
        $reimbursement->delete();
        return redirect()->route('reimbursements.index')->with('success', 'Reimbursement deleted successfully.');
    }
}
