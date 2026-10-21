<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Services\LeavePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveTypeController extends Controller
{
    private function writable(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true), 403);
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:all,active,inactive']);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $types = LeaveType::where('company_id', $request->user()->company_id)->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))->orderBy('name')->paginate(15)->withQueryString();

        return view('pages.leave-types.index', compact('types', 'search', 'status'));
    }

    public function create(Request $request)
    {
        $this->writable($request);

        return view('pages.leave-types.form', ['type' => new LeaveType, 'events' => collect()]);
    }

    public function edit(Request $request, int $leave_type)
    {
        $this->writable($request);
        $type = LeaveType::where('company_id', $request->user()->company_id)->findOrFail($leave_type);
        $events = DB::table('leave_type_events as e')->join('users as u', 'u.id', '=', 'e.actor_id')->where('e.company_id', $request->user()->company_id)->where('e.leave_type_id', $type->id)->orderByDesc('e.version')->select('e.*', 'u.name as actor_name')->limit(30)->get();

        return view('pages.leave-types.form', compact('type', 'events'));
    }

    private function validated(Request $request): array
    {
        $this->writable($request);
        $request->merge(['code' => is_string($request->code) ? strtoupper(trim($request->code)) : $request->code]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/D'],
            'name' => 'required|string|max:120', 'is_active' => 'required|boolean',
            'deducts_balance' => 'required|boolean', 'annual_quota_days' => ['nullable', \Illuminate\Validation\Rule::requiredIf(fn () => $request->boolean('deducts_balance')), 'integer', 'min:0', 'max:366'],
            'requires_attachment' => 'required|boolean', 'requirements' => 'nullable|string|max:2000',
            'version' => 'required|integer|min:0', 'change_reason' => 'required|string|max:1000',
        ]);
        $data['requirements'] = $data['requirements'] ?? null;
        $data['annual_quota_days'] = $data['annual_quota_days'] ?? null;
        if (! $data['deducts_balance']) {
            $data['annual_quota_days'] = null;
        }

        return $data;
    }

    public function store(Request $request, LeavePolicy $policy)
    {
        $type = $policy->save($request->user(), $this->validated($request));

        return redirect()->route('leave-types.edit', $type)->with('success', 'Jenis cuti ditambahkan. Kuota belum dialokasikan menjadi saldo karyawan.');
    }

    public function update(Request $request, int $leave_type, LeavePolicy $policy)
    {
        $policy->save($request->user(), $this->validated($request), $leave_type);

        return redirect()->route('leave-types.edit', $leave_type)->with('success', 'Kebijakan diperbarui untuk pengajuan berikutnya. Riwayat pengajuan tetap dipertahankan.');
    }
}
