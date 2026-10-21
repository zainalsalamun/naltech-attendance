<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected', 'cancelled'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all'];
        $overtimes = Overtime::visibleTo(request()->user())->with('user')
            ->when($filters['name'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.overtime.index', compact('overtimes', 'filters'));
    }

    public function create()
    {
        $users = User::when(request()->user()->role === 'supervisor', fn ($q) => $q->where(fn ($q) => $q->where('id', auth()->id())->orWhere('manager_id', auth()->id())))->orderBy('name')->get();

        return view('pages.overtime.create', compact('users'));
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'user_id' => ['required', Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
        ]);
        if ($request->user()->role === 'supervisor') {
            abort_unless(User::whereKey($data['user_id'])->where(fn ($q) => $q->where('id', $request->user()->id)->orWhere('manager_id', $request->user()->id))->exists(), 403);
        }
        $data['status'] = 'pending';
        Overtime::create($data);

        return redirect()->route('overtimes.index')->with('success', 'Pengajuan lembur berhasil ditambahkan.');
    }

    public function show(string $id)
    {
        $overtime = Overtime::visibleTo(request()->user())->with('user')->findOrFail($id);

        return view('pages.overtime.show', array_merge(compact('overtime'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('overtime', $overtime)]));
    }

    public function edit(string $id)
    {
        $overtime = Overtime::visibleTo(request()->user())->with('user')->findOrFail($id);

        return view('pages.overtime.edit', array_merge(compact('overtime'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('overtime', $overtime)]));
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate($this->rules() + ['status' => ['sometimes', 'in:pending'], 'approval_version' => ['required', 'integer', 'min:0']]);
        unset($data['status'], $data['approval_version']);
        app(\App\Services\SubmissionApproval::class)->edit($request->user(), 'overtime', (int) $id, (int) $request->input('approval_version'), function ($locked) use ($data) {
            $locked->update($data);

            return $locked;
        });

        return redirect()->route('overtimes.index')->with('success', 'Pengajuan lembur berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        Overtime::visibleTo(request()->user())->findOrFail($id);

        return app(SubmissionApprovalController::class)->cancel(request(), 'overtime', (int) $id);
    }
}
