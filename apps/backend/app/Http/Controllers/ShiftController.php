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
        return view('pages.shifts.create', ['timezone' => $this->timezone()]);
    }

    private function timezone(): string
    {
        return app(\App\Services\WorkCalendar::class)->timezone(request()->user()->company()->firstOrFail());
    }

    private function validatedShift(Request $request): array
    {
        return \App\Support\ShiftInput::validate($request);
    }

    public function store(Request $request)
    {
        Shift::create($this->validatedShift($request));

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function show(Shift $shift)
    {
        $shift->loadCount('users');

        return view('pages.shifts.show', array_merge(compact('shift'), ['timezone' => $this->timezone()]));
    }

    public function edit(Shift $shift)
    {
        return view('pages.shifts.edit', array_merge(compact('shift'), ['timezone' => $this->timezone()]));
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validatedShift($request));

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy(Shift $shift)
    {
        if (app(\App\Services\EffectiveShift::class)->ready()) {
            abort_if(\Illuminate\Support\Facades\DB::table('employee_shift_assignments')->where('shift_id', $shift->id)->exists(), 409, 'Shift memiliki riwayat penempatan; pertahankan master atau buat shift baru.');
        }
        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dihapus. Karyawan terkait kini tidak memiliki shift.');
    }
}
