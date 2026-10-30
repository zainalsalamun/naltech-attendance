<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        LeaveBalanceService::checkReady();

        $company = $request->user()->company;
        $currentYear = (int) CarbonImmutable::now()->year;

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'name' => ['nullable', 'string', 'max:100'],
            'leave_type_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $selectedYear = (int) ($filters['year'] ?? $currentYear);
        $nameFilter = trim($filters['name'] ?? '');
        $typeFilter = ! empty($filters['leave_type_id']) ? (int) $filters['leave_type_id'] : null;

        $balancesQuery = LeaveBalance::where('company_id', $company->id)
            ->where('period_year', $selectedYear)
            ->with(['user', 'leaveType'])
            ->when($nameFilter !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$nameFilter}%")))
            ->when($typeFilter, fn ($q) => $q->where('leave_type_id', $typeFilter))
            ->orderByDesc('id');

        $balances = $balancesQuery->paginate(15)->withQueryString();

        // Summary metrics
        $allYearBalances = LeaveBalance::where('company_id', $company->id)
            ->where('period_year', $selectedYear)
            ->get();

        $summary = [
            'total_employees_allocated' => $allYearBalances->pluck('user_id')->unique()->count(),
            'total_allocated_days' => $allYearBalances->sum('allocated_days'),
            'total_reserved_days' => $allYearBalances->sum('reserved_days'),
            'total_used_days' => $allYearBalances->sum('used_days'),
            'total_remaining_days' => $allYearBalances->sum('remaining_days'),
        ];

        // Eligible leave types and active users for modal allocation
        $leaveTypes = LeaveType::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('deducts_balance', true)
            ->orderBy('name')
            ->get();

        $employees = User::where('company_id', $company->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        return view('pages.leave_balances.index', compact(
            'balances',
            'summary',
            'selectedYear',
            'nameFilter',
            'typeFilter',
            'leaveTypes',
            'employees',
            'company'
        ));
    }

    public function store(Request $request)
    {
        LeaveBalanceService::checkReady();

        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')->where('company_id', $companyId)->where('deducts_balance', true),
            ],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'allocated_days' => ['required', 'integer', 'min:0', 'max:366'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $targetUser = User::findOrFail($data['user_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        app(LeaveBalanceService::class)->allocate(
            $request->user(),
            $targetUser,
            $leaveType,
            (int) $data['period_year'],
            (int) $data['allocated_days'],
            $data['reason']
        );

        return redirect()->route('leave-balances.index', ['year' => $data['period_year']])
            ->with('success', "Alokasi saldo cuti untuk {$targetUser->name} berhasil diperbarui.");
    }

    public function batchAllocate(Request $request)
    {
        LeaveBalanceService::checkReady();

        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')->where('company_id', $companyId)->where('deducts_balance', true),
            ],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'allocated_days' => ['required', 'integer', 'min:0', 'max:366'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $employees = User::where('company_id', $companyId)
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('employment_status')->orWhere('employment_status', 'active'))
            ->get();

        $service = app(LeaveBalanceService::class);
        $count = 0;
        foreach ($employees as $employee) {
            $service->allocate(
                $request->user(),
                $employee,
                $leaveType,
                (int) $data['period_year'],
                (int) $data['allocated_days'],
                $data['reason']
            );
            $count++;
        }

        return redirect()->route('leave-balances.index', ['year' => $data['period_year']])
            ->with('success', "Alokasi saldo cuti massal berhasil diterapkan kepada {$count} karyawan aktif.");
    }

    public function ledger(Request $request)
    {
        LeaveBalanceService::checkReady();

        $company = $request->user()->company;
        $currentYear = (int) CarbonImmutable::now()->year;

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'transaction_type' => ['nullable', 'string', 'max:32'],
        ]);

        $selectedYear = (int) ($filters['year'] ?? $currentYear);
        $userId = ! empty($filters['user_id']) ? (int) $filters['user_id'] : null;
        $transactionType = $filters['transaction_type'] ?? null;

        $ledgers = LeaveBalanceLedger::where('company_id', $company->id)
            ->whereYear('created_at', $selectedYear)
            ->with(['user', 'actor', 'leaveBalance.leaveType', 'permission'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($transactionType, fn ($q) => $q->where('transaction_type', $transactionType))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $employees = User::where('company_id', $company->id)->orderBy('name')->get();

        return view('pages.leave_balances.ledger', compact('ledgers', 'selectedYear', 'userId', 'transactionType', 'employees', 'company'));
    }

    /**
     * Preview date calculation and balance impact (used by Web AJAX and API).
     */
    public function calculate(Request $request)
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'leave_type_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json(['success' => true] + app(LeaveBalanceService::class)->preview($request->user(), $data));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
