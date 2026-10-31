<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        $types = LeaveType::where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->paginate(50);

        return response()->json(['data' => $types->getCollection()->map(fn ($type) => $type->policy()), 'meta' => ['current_page' => $types->currentPage(), 'last_page' => $types->lastPage()]]);
    }
}
