<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'filter' => ['nullable', Rule::in(['all', 'late', 'absent'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $company = $request->user()->company()->firstOrFail();
        $calendar = app(\App\Services\WorkCalendar::class);
        $timezone = $calendar->timezone($company);
        $today = now($timezone)->toDateString();
        $date = $validated['date'] ?? $today;
        $calendarDay = $calendar->day($company, $date);
        $filter = $validated['filter'] ?? 'all';
        $search = trim($validated['q'] ?? '');
        $viewer = $request->user();
        $manager = in_array($viewer->role, ['admin', 'super admin', 'hrd', 'supervisor']);
        $company = Company::findOrFail($viewer->company_id);

        // One row per company member; only the selected date's attendance is joined.
        // Calendar applies company-wide; effective shift snapshots are a later slice.
        $approvalReady = \Illuminate\Support\Facades\Schema::hasColumn('permissions', 'status');
        $approvedPredicate = $approvalReady ? "p.status = 'approved'" : 'p.is_approved = 1';
        $members = User::query()->where('users.company_id', $company->id)
            ->when($date === $today && \Illuminate\Support\Facades\Schema::hasColumn('users', 'employment_status'),
                fn ($q) => $q->where('users.employment_status', 'active')->whereNull('users.archived_at'))
            ->when(! $manager, fn ($q) => $q->where('users.id', $viewer->id))
            ->leftJoin('shifts as s', function ($join) use ($company) {
                $join->on('s.id', '=', 'users.shift_id')->where('s.company_id', $company->id);
            })
            ->leftJoin('attendances as a', function ($join) use ($date) {
                $join->on('a.user_id', '=', 'users.id')->where('a.date', $date);
            })
            ->select('users.id', 'users.name', 'users.department', 's.name as shift_name',
                's.time_in as shift_in', 's.time_out as shift_out', 'a.time_in', 'a.time_out',
                'a.latlon_in', 'a.latlon_out')
            ->selectRaw("CASE
                WHEN a.time_in IS NOT NULL AND (s.id IS NULL OR s.time_in IS NULL OR s.time_out IS NULL OR s.time_out <= s.time_in) THEN 'review'
                WHEN a.time_in IS NOT NULL AND a.time_in > s.time_in THEN 'late'
                WHEN a.time_in IS NOT NULL THEN 'ontime'
                WHEN ? = 1 THEN 'off'
                WHEN EXISTS (SELECT 1 FROM permissions p WHERE p.user_id = users.id AND p.date_permission = ? AND {$approvedPredicate}) THEN 'leave'
                ELSE 'absent' END as attendance_status", [$calendarDay['working'] === false ? 1 : 0, $date]);

        $counts = DB::query()->fromSub((clone $members)->toBase(), 'members')
            ->selectRaw('attendance_status, COUNT(*) as total')->groupBy('attendance_status')
            ->pluck('total', 'attendance_status');
        $summary = [
            'total' => (int) $counts->sum(),
            'present' => (int) ($counts['ontime'] ?? 0) + (int) ($counts['late'] ?? 0) + (int) ($counts['review'] ?? 0),
            'ontime' => (int) ($counts['ontime'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'leave' => (int) ($counts['leave'] ?? 0),
            'review' => (int) ($counts['review'] ?? 0),
            'off' => (int) ($counts['off'] ?? 0),
        ];
        $rows = DB::query()->fromSub($members->toBase(), 'members')
            ->when($filter !== 'all', fn ($q) => $q->where('attendance_status', $filter))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->paginate(8)->withQueryString();

        // Only canonical pending rows are actionable; legacy unknown rows need reconciliation.
        $permissions = Permission::with('user:id,name')->when($approvalReady, fn ($q) => $q->where('status', 'pending'), fn ($q) => $q->where('is_approved', false))
            ->visibleTo($viewer);
        $overtimes = Overtime::with('user:id,name')->where('status', 'pending')
            ->visibleTo($viewer);
        $permissionCount = (clone $permissions)->count();
        $overtimeCount = (clone $overtimes)->count();

        return view('pages.dashboard', [
            'company' => $company, 'manager' => $manager, 'summary' => $summary,
            'rows' => $rows, 'date' => $date, 'filter' => $filter, 'search' => $search,
            'selectedDate' => CarbonImmutable::parse($date)->locale('id'),
            'permissions' => $permissions->latest()->limit(3)->get(),
            'overtimes' => $overtimes->latest()->limit(3)->get(),
            'approvalReady' => $approvalReady, 'permissionCount' => $permissionCount, 'overtimeCount' => $overtimeCount,
            'generatedAt' => now($timezone), 'timezone' => $timezone, 'today' => $today, 'calendarDay' => $calendarDay,
        ]);
    }
}
