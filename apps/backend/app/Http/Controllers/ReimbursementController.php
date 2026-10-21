<?php

namespace App\Http\Controllers;

use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected', 'cancelled'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all', 'user_id' => $filters['user_id'] ?? ''];
        $reimbursements = Reimbursement::visibleTo(request()->user())->with('user')
            ->when($filters['name'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['user_id'] !== '', fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.reimbursements.index', compact('reimbursements', 'filters'));
    }

    public function create()
    {
        $users = User::when(request()->user()->role === 'supervisor', fn ($q) => $q->where(fn ($q) => $q->where('id', auth()->id())->orWhere('manager_id', auth()->id())))->orderBy('name')->get();

        return view('pages.reimbursements.create', compact('users'));
    }

    private function rules(Request $request): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:10000'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));
        if ($request->user()->role === 'supervisor') {
            abort_unless(User::whereKey($data['user_id'])->where(fn ($q) => $q->where('id', $request->user()->id)->orWhere('manager_id', $request->user()->id))->exists(), 403);
        }
        $data['status'] = 'pending';
        app(\App\Services\SubmissionAttachment::class)->save(new Reimbursement, $data, $request->file('image'), 'reimbursements');

        return redirect()->route('reimbursements.index')->with('success', 'Pengajuan reimbursement berhasil ditambahkan.');
    }

    public function show(Reimbursement $reimbursement)
    {
        abort_unless(Reimbursement::visibleTo(request()->user())->whereKey($reimbursement->id)->exists(), 404);
        $reimbursement->load('user');

        return view('pages.reimbursements.show', array_merge(compact('reimbursement'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('reimbursement', $reimbursement)]));
    }

    public function edit(Reimbursement $reimbursement)
    {
        abort_unless(Reimbursement::visibleTo(request()->user())->whereKey($reimbursement->id)->exists(), 404);
        $reimbursement->load('user');
        $users = User::when(request()->user()->role === 'supervisor', fn ($q) => $q->where(fn ($q) => $q->where('id', auth()->id())->orWhere('manager_id', auth()->id())))->orderBy('name')->get();

        return view('pages.reimbursements.edit', array_merge(compact('reimbursement', 'users'), ['approvalEvents' => app(\App\Services\SubmissionApproval::class)->history('reimbursement', $reimbursement)]));
    }

    public function update(Request $request, Reimbursement $reimbursement)
    {
        $data = $request->validate($this->rules($request) + ['status' => ['sometimes', 'in:pending'], 'approval_version' => ['required', 'integer', 'min:0']]);
        unset($data['image'], $data['status'], $data['approval_version']);
        abort_unless((int) $data['user_id'] === (int) $reimbursement->user_id, 422, 'Pemilik pengajuan tidak dapat diganti.');
        app(\App\Services\SubmissionApproval::class)->edit($request->user(), 'reimbursement', $reimbursement->id, (int) $request->input('approval_version'),
            fn ($locked) => app(\App\Services\SubmissionAttachment::class)->save($locked, $data, $request->file('image'), 'reimbursements'));

        return redirect()->route('reimbursements.index')->with('success', 'Pengajuan reimbursement berhasil diperbarui.');
    }

    public function destroy(Reimbursement $reimbursement)
    {
        $request = request();

        return app(SubmissionApprovalController::class)->cancel($request, 'reimbursement', $reimbursement->id);
    }
}
