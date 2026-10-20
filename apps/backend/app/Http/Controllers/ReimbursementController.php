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
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all', 'user_id' => $filters['user_id'] ?? ''];
        $reimbursements = Reimbursement::with('user')
            ->when($filters['name'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['user_id'] !== '', fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.reimbursements.index', compact('reimbursements', 'filters'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();

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
        $data['image'] = $request->hasFile('image') ? $request->file('image')->store('assets/reimbursements', 'public') : null;
        $data['status'] = 'pending';
        Reimbursement::create($data);

        return redirect()->route('reimbursements.index')->with('success', 'Pengajuan reimbursement berhasil ditambahkan.');
    }

    public function show(Reimbursement $reimbursement)
    {
        $reimbursement->load('user');

        return view('pages.reimbursements.show', compact('reimbursement'));
    }

    public function edit(Reimbursement $reimbursement)
    {
        $reimbursement->load('user');
        $users = User::orderBy('name')->get();

        return view('pages.reimbursements.edit', compact('reimbursement', 'users'));
    }

    public function update(Request $request, Reimbursement $reimbursement)
    {
        $data = $request->validate($this->rules($request) + ['status' => ['required', Rule::in(['pending', 'approved', 'rejected'])]]);
        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('assets/reimbursements', 'public');
        }
        $reimbursement->update($data);

        return redirect()->route('reimbursements.index')->with('success', 'Pengajuan reimbursement berhasil diperbarui.');
    }

    public function destroy(Reimbursement $reimbursement)
    {
        $reimbursement->delete();

        return redirect()->route('reimbursements.index')->with('success', 'Pengajuan reimbursement berhasil dihapus.');
    }
}
