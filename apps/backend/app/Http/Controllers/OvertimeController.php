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
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all'];
        $overtimes = Overtime::with('user')
            ->when($filters['name'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.overtime.index', compact('overtimes', 'filters'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();

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
        $data['status'] = 'pending';
        Overtime::create($data);

        return redirect()->route('overtimes.index')->with('success', 'Pengajuan lembur berhasil ditambahkan.');
    }

    public function show(string $id)
    {
        $overtime = Overtime::with('user')->findOrFail($id);

        return view('pages.overtime.show', compact('overtime'));
    }

    public function edit(string $id)
    {
        $overtime = Overtime::with('user')->findOrFail($id);

        return view('pages.overtime.edit', compact('overtime'));
    }

    public function update(Request $request, string $id)
    {
        $overtime = Overtime::findOrFail($id);
        $data = $request->validate($this->rules() + ['status' => ['required', Rule::in(['pending', 'approved', 'rejected'])]]);
        $overtime->update($data);

        return redirect()->route('overtimes.index')->with('success', 'Pengajuan lembur berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        Overtime::findOrFail($id)->delete();

        return redirect()->route('overtimes.index')->with('success', 'Pengajuan lembur berhasil dihapus.');
    }
}
