<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    //show
    public function show($id)
    {
        $company = Company::findOrFail($id);

        return view('pages.company.show', compact('company'));
    }

    //edit
    public function edit($id)
    {
        $company = Company::findOrFail($id);

        return view('pages.company.edit', compact('company'));
    }

    //update
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['required', 'numeric', 'gt:0', function ($attribute, $value, $fail) {
                if (! is_finite((float) $value)) {
                    $fail('Radius harus berupa angka yang valid.');
                }
            }],
            'time_in' => ['required', 'date_format:H:i,H:i:s'],
            'time_out' => ['required', 'date_format:H:i,H:i:s'],
            'attendance_type' => ['required', 'in:None,QR'],
            'overtime_rate' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);
        $validated['overtime_rate'] = $validated['overtime_rate'] ?? 0;
        $company->update($validated);

        return redirect()->route('companies.show', $company->id)->with('success', 'Informasi perusahaan berhasil diperbarui.');
    }
}
