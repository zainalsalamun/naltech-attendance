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
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'address' => 'required',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'required|numeric|gt:0',
            'time_in' => 'required',
            'time_out' => 'required',
            'attendance_type' => 'required|in:None,QR',
            'overtime_rate' => 'nullable|numeric',
        ]);

        $company->update([
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'radius_km' => $request->radius_km,
            'time_in' => $request->time_in,
            'time_out' => $request->time_out,
            'attendance_type' => $request->attendance_type,
            'overtime_rate' => $request->overtime_rate ?? 0,
        ]);

        return redirect()->route('companies.show', $company->id)->with('success', 'Company updated successfully');
    }
}
