<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['name'] ?? '');
        $shifts = Shift::withCount('users')->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.shifts.index', compact('shifts', 'search'));
    }

    public function create()
    {
        return view('pages.shifts.create');
    }

    private function validatedShift(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'time_in' => ['required', 'date_format:H:i,H:i:s'],
            'time_out' => ['required', 'date_format:H:i,H:i:s'],
        ]);
        foreach (['time_in', 'time_out'] as $field) {
            if (strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }
        if ($data['time_in'] === $data['time_out']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['time_out' => 'Jam pulang harus berbeda dari jam masuk.']);
        }

        return $data;
    }

    public function store(Request $request)
    {
        Shift::create($this->validatedShift($request));

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function show(Shift $shift)
    {
        $shift->loadCount('users');

        return view('pages.shifts.show', compact('shift'));
    }

    public function edit(Shift $shift)
    {
        return view('pages.shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validatedShift($request));

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dihapus. Karyawan terkait kini tidak memiliki shift.');
    }
}
