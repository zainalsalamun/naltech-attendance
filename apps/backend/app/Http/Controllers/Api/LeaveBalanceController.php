<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        if (! LeaveBalanceService::ready()) {
            return response()->json([
                'data' => [],
                'message' => 'Layanan saldo cuti belum diaktifkan.',
            ]);
        }

        $user = $request->user();
        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);

        $balances = LeaveBalance::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('period_year', $year)
            ->with(['leaveType' => fn ($q) => $q->select('id', 'code', 'name', 'deducts_balance', 'annual_quota_days', 'requires_attachment')])
            ->get();

        return response()->json([
            'data' => $balances,
            'year' => $year,
        ]);
    }

    public function calculate(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'leave_type_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json(['data' => app(LeaveBalanceService::class)->preview($request->user(), $data)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function ledger(Request $request)
    {
        if (! LeaveBalanceService::ready()) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);

        $ledgers = LeaveBalanceLedger::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereYear('created_at', $year)
            ->with(['leaveBalance.leaveType:id,code,name', 'actor:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $ledgers->items(),
            'meta' => [
                'current_page' => $ledgers->currentPage(),
                'last_page' => $ledgers->lastPage(),
            ],
        ]);
    }
}
