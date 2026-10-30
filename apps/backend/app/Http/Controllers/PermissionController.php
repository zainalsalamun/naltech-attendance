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
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'pending', 'approved', 'rejected', 'cancelled', 'cancellation_requested', 'needs_review', 'unapproved'])],
            'action_context' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'actionable', 'waiting_others'])],
            'context' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'actionable', 'waiting_others'])],
        ]);
        $actionContext = $filters['action_context'] ?? $filters['context'] ?? 'all';
        $filters = [
            'name' => trim($filters['name'] ?? ''),
            'date' => $filters['date'] ?? '',
            'status' => $filters['status'] ?? 'all',
            'action_context' => $actionContext,
        ];
        $permissions = Permission::visibleTo($request->user())->with(['user.manager', 'leaveType'])
            ->when($filters['name'] !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($query) => $query->where(function ($q) use ($filters) {
                $d = $filters['date'];
                $q->where(fn ($sub) => $sub->whereNotNull('start_date')->where('start_date', '<=', $d)->where('end_date', '>=', $d))
                    ->orWhere(fn ($sub) => $sub->whereNull('start_date')->where('date_permission', $d));
            }))
            ->when($filters['status'] !== 'all', fn ($query) => $filters['status'] === 'unapproved' ? $query->where('status', '!=', 'approved') : $query->where('status', $filters['status']))
            ->withActionContext($request->user(), $filters['action_context'])
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.permission.index', compact('permissions', 'filters'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $types = \App\Models\LeaveType::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get();
        $year = (int) \Carbon\CarbonImmutable::now()->year;

        $userBalances = \App\Services\LeaveBalanceService::ready()
            ? \App\Models\LeaveBalance::where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->where('period_year', $year)
                ->get()
                ->keyBy('leave_type_id')
            : collect();

        return view('pages.permission.create', compact('types', 'userBalances', 'year'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'reason' => 'required|string|max:5000',
            'leave_type_id' => 'nullable|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (empty($data['start_date']) && ! empty($data['date'])) {
            $data['start_date'] = $data['date'];
            $data['end_date'] = $data['date'];
        } elseif (! empty($data['start_date']) && empty($data['end_date'])) {
            $data['end_date'] = $data['start_date'];
        }

        if (empty($data['start_date'])) {
            return back()->withInput()->withErrors(['start_date' => 'Tanggal awal pengajuan cuti/izin wajib dipilih.']);
        }

        $row = app(\App\Services\LeavePolicy::class)->submit($request->user(), $data, $request->file('image'));

        return redirect()->route('permissions.show', $row)->with('success', 'Pengajuan Anda berhasil dikirim untuk ditinjau.');
    }

    //view
    public function show($id)
    {
        $permission = Permission::visibleTo(request()->user())->with(['user', 'leaveType'])->findOrFail($id);

        $employeeBalance = null;
        if ($permission->leave_type_id && \App\Services\LeaveBalanceService::ready()) {
            $year = (int) \Carbon\CarbonImmutable::parse($permission->start_date ?: $permission->date_permission)->year;
            $employeeBalance = \App\Models\LeaveBalance::where('company_id', $permission->user->company_id)
                ->where('user_id', $permission->user_id)
                ->where('leave_type_id', $permission->leave_type_id)
                ->where('period_year', $year)
                ->first();
        }

        return view('pages.permission.show', array_merge(
            compact('permission', 'employeeBalance'),
            ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('permission', $permission)]
        ));
    }

    //edit
    public function edit($id)
    {
        $permission = Permission::visibleTo(request()->user())->with(['user', 'leaveType'])->findOrFail($id);

        $employeeBalance = null;
        if ($permission->leave_type_id && \App\Services\LeaveBalanceService::ready()) {
            $year = (int) \Carbon\CarbonImmutable::parse($permission->start_date ?: $permission->date_permission)->year;
            $employeeBalance = \App\Models\LeaveBalance::where('company_id', $permission->user->company_id)
                ->where('user_id', $permission->user_id)
                ->where('leave_type_id', $permission->leave_type_id)
                ->where('period_year', $year)
                ->first();
        }

        return view('pages.permission.edit', array_merge(
            compact('permission', 'employeeBalance'),
            ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('permission', $permission)]
        ));
    }

    //update
    public function update(Request $request, $id)
    {
        return app(\App\Http\Controllers\SubmissionApprovalController::class)->decision($request, 'permission', (int) $id);
    }
}
