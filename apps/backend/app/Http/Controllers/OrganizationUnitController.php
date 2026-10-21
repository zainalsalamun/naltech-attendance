<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationUnitController extends Controller
{
    private function authorizeWrite(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true), 403);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])]]);
        $search = trim($data['q'] ?? '');
        $status = $data['status'] ?? 'active';
        $units = OrganizationUnit::where('company_id', $request->user()->company_id)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->withCount(['users' => fn ($q) => $q->where('company_id', $request->user()->company_id)])
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('pages.units.index', compact('units', 'search', 'status'));
    }

    public function create(Request $request)
    {
        $this->authorizeWrite($request);

        return view('pages.units.form', ['unit' => new OrganizationUnit]);
    }

    public function edit(Request $request, OrganizationUnit $unit)
    {
        $this->authorizeWrite($request);

        return view('pages.units.form', compact('unit'));
    }

    public function store(Request $request)
    {
        return $this->persist($request, new OrganizationUnit);
    }

    public function update(Request $request, OrganizationUnit $unit)
    {
        return $this->persist($request, $unit);
    }

    private function persist(Request $request, OrganizationUnit $unit)
    {
        $this->authorizeWrite($request);
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'regex:~^[A-Z0-9][A-Z0-9._/-]*$~D', Rule::unique('organization_units', 'code')->where('company_id', $request->user()->company_id)->ignore($unit->id)], 'name' => ['required', 'string', 'max:150'], 'is_active' => ['required', 'boolean']]);
        try {
            DB::transaction(function () use ($request, $unit, $data) {
                Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
                if ($unit->exists) {
                    $unit = OrganizationUnit::where('company_id', $request->user()->company_id)->whereKey($unit->id)->lockForUpdate()->firstOrFail();
                }
                $unit->fill($data);
                $unit->company_id = $request->user()->company_id;
                $unit->save();
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'Kode unit sudah digunakan dalam perusahaan.']);
        }

        return redirect()->route('units.index')->with('success', 'Unit berhasil disimpan. Penempatan karyawan tetap dipertahankan.');
    }
}
