<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    //index
    public function index(Request $request)
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'pending', 'approved', 'rejected', 'cancelled', 'needs_review', 'unapproved'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all'];
        $permissions = Permission::visibleTo($request->user())->with('user')
            ->when($filters['name'] !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($query) => $query->where('date_permission', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($query) => $filters['status'] === 'unapproved' ? $query->where('status', '!=', 'approved') : $query->where('status', $filters['status']))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.permission.index', compact('permissions', 'filters'));
    }

    public function create(Request $request)
    {
        $types = \App\Models\LeaveType::where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get();

        return view('pages.permission.create', compact('types'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:5000', 'leave_type_id' => 'nullable|integer|min:1', 'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048']);
        $row = app(\App\Services\LeavePolicy::class)->submit($request->user(), $data, $request->file('image'));

        return redirect()->route('permissions.show', $row)->with('success', 'Pengajuan Anda dikirim untuk ditinjau.');
    }

    //view
    public function show($id)
    {
        $permission = Permission::visibleTo(request()->user())->with('user')->findOrFail($id);

        return view('pages.permission.show', array_merge(compact('permission'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('permission', $permission)]));
    }

    //edit
    public function edit($id)
    {
        $permission = Permission::visibleTo(request()->user())->with('user')->findOrFail($id);

        return view('pages.permission.edit', array_merge(compact('permission'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('permission', $permission)]));
    }

    //update
    public function update(Request $request, $id)
    {
        return app(\App\Http\Controllers\SubmissionApprovalController::class)->decision($request, 'permission', (int) $id);
    }
}
