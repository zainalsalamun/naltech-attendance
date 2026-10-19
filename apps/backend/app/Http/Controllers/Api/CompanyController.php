<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;

class CompanyController extends Controller
{
    //show
    public function show(Request $request)
    {
        $company = $request->user()->company()->firstOrFail();
        return response(['company' => $company], 200);
    }
}
