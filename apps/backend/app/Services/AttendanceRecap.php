<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceRecap
{
    public function __construct(
        protected WorkCalendar $calendar,
        protected EffectiveShift $effectiveShift
    ) {}

    /**
     * Validate and normalize filter inputs.
     * Enforces maximum 90 days inclusive range and timezone awareness.
     */
    public function validateFilters(Request $request, Company $company, ?User $viewer = null): array
    {
        $tz = $this->calendar->timezone($company);
        $now = CarbonImmutable::now($tz);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
        ]);

        $start = $validated['start_date'] ?? $now->startOfMonth()->toDateString();
        $end = $validated['end_date'] ?? $now->toDateString();

        if ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) >= 90) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang rekap harus berurutan dan maksimal 90 hari, termasuk tanggal awal dan akhir.',
            ]);
        }

        $userId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;
        if ($userId) {
            $userCheck = User::where('company_id', $company->id)
                ->when($viewer && $viewer->role === 'supervisor', fn ($q) => $q->where('manager_id', $viewer->id))
                ->whereKey($userId)
                ->first();

            abort_unless($userCheck !== null, 404, 'Karyawan tidak ditemukan dalam cakupan Anda.');
        }

        $unitId = ! empty($validated['organization_unit_id']) ? (int) $validated['organization_unit_id'] : null;
        if ($unitId) {
            $unitCheck = OrganizationUnit::where('company_id', $company->id)->whereKey($unitId)->first();
            abort_unless($unitCheck !== null, 404, 'Unit organisasi tidak ditemukan.');
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
            'name' => trim($validated['name'] ?? ''),
            'user_id' => $userId,
            'organization_unit_id' => $unitId,
        ];
    }

    /**
     * Query matching employees with company and supervisor scoping.
     * Historical employment: includes inactive/archived employees whose active employment overlapped the period.
     */
    public function baseEmployeeQuery(Company $company, array $filters, ?User $viewer = null): Builder
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $name = $filters['name'];
        $userId = $filters['user_id'];
        $unitId = $filters['organization_unit_id'];

        return User::where('company_id', $company->id)
            ->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $endDate))
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>=', $startDate))
            ->when($viewer && $viewer->role === 'supervisor', fn ($q) => $q->where('manager_id', $viewer->id))
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->when($userId !== null, fn ($q) => $q->where('id', $userId))
            ->when($unitId !== null, fn ($q) => $q->where('organization_unit_id', $unitId))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Calculate summary and paginated recap table for index view.
     */
    public function calculate(Company $company, array $filters, ?User $viewer = null, int $perPage = 15, int $page = 1): array
    {
        $tz = $this->calendar->timezone($company);
        $today = CarbonImmutable::now($tz)->toDateString();
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $dates = $this->dateRange($startDate, $endDate);
        $calendarDays = $this->calendar->days($company, $dates);
        $scheduledDates = [];
        foreach ($dates as $d) {
            if (($calendarDays[$d]['working'] ?? null) === true) {
                $scheduledDates[] = $d;
            }
        }
        $scheduledWorkingDaysCount = count($scheduledDates);

        $query = $this->baseEmployeeQuery($company, $filters, $viewer);
        $totalEmployees = (clone $query)->count();

        // Calculate summary across all filtered employees using chunked processing to keep memory bounded
        $summary = $this->calculateSummaryChunked(
            (clone $query),
            $company,
            $startDate,
            $endDate,
            $scheduledDates,
            $scheduledWorkingDaysCount,
            $today,
            $totalEmployees
        );

        // Paginate employees for table view (only materializes active page into memory)
        $paginatedEmployees = (clone $query)->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $pageRecap = $this->calculateRecapRows(
            $paginatedEmployees->getCollection(),
            $company,
            $startDate,
            $endDate,
            $scheduledDates,
            $today
        );

        return [
            'recap' => $pageRecap,
            'paginator' => $paginatedEmployees,
            'summary' => $summary,
            'calendarDays' => $calendarDays,
            'timezone' => $tz,
            'today' => $today,
        ];
    }

    /**
     * Calculate full recap dataset.
     * Uses chunked processing internally to prevent unbounded memory spikes.
     */
    public function calculateFull(Company $company, array $filters, ?User $viewer = null, int $chunkSize = 100): array
    {
        $tz = $this->calendar->timezone($company);
        $today = CarbonImmutable::now($tz)->toDateString();
        $rows = [];

        $this->streamRecapRows($company, $filters, $viewer, function ($row) use (&$rows) {
            $rows[] = $row;
        }, $chunkSize);

        return [
            'recap' => $rows,
            'timezone' => $tz,
            'today' => $today,
        ];
    }

    /**
     * Stream recap rows across all matching employees in chunks.
     * Invokes $callback($row) for each computed employee recap row without materializing all records into memory.
     */
    public function streamRecapRows(
        Company $company,
        array $filters,
        ?User $viewer,
        callable $callback,
        int $chunkSize = 100
    ): void {
        $tz = $this->calendar->timezone($company);
        $today = CarbonImmutable::now($tz)->toDateString();
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $dates = $this->dateRange($startDate, $endDate);
        $calendarDays = $this->calendar->days($company, $dates);
        $scheduledDates = [];
        foreach ($dates as $d) {
            if (($calendarDays[$d]['working'] ?? null) === true) {
                $scheduledDates[] = $d;
            }
        }

        $query = $this->baseEmployeeQuery($company, $filters, $viewer);

        (clone $query)->chunk($chunkSize, function (Collection $employeeChunk) use (
            $company,
            $startDate,
            $endDate,
            $scheduledDates,
            $today,
            $callback
        ) {
            $rows = $this->calculateRecapRows(
                $employeeChunk,
                $company,
                $startDate,
                $endDate,
                $scheduledDates,
                $today
            );

            foreach ($rows as $row) {
                $callback($row);
            }
        });
    }

    /**
     * Daily drilldown breakdown for a single employee in date range.
     */
    public function dailyBreakdown(Company $company, User $user, string $startDate, string $endDate): array
    {
        $tz = $this->calendar->timezone($company);
        $today = CarbonImmutable::now($tz)->toDateString();
        $dates = $this->dateRange($startDate, $endDate);
        $calendarDays = $this->calendar->days($company, $dates);

        // Load attendances in date range
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy('date');

        // Load permissions in date range (approved or cancellation_requested)
        $approvalReady = Schema::hasColumn('permissions', 'status');
        $permissions = Permission::where('user_id', $user->id)
            ->when($approvalReady, fn ($q) => $q->whereIn('status', ['approved', 'cancellation_requested']), fn ($q) => $q->where('is_approved', true))
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sub) use ($startDate, $endDate) {
                    $sub->whereNotNull('start_date')
                        ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                })->orWhere(function ($sub) use ($startDate, $endDate) {
                    $sub->whereNull('start_date')
                        ->whereBetween('date_permission', [$startDate, $endDate]);
                });
            })
            ->get();

        $days = [];
        foreach ($dates as $date) {
            $cal = $calendarDays[$date] ?? ['working' => null, 'label' => 'Belum dikonfigurasi'];
            $isWorking = ($cal['working'] === true);
            $isHoliday = ($cal['working'] === false);

            $att = $attendances->get($date);

            // Find matching permission covering this date
            $perm = $permissions->first(function ($p) use ($date) {
                $pStart = $p->start_date ? $p->start_date->format('Y-m-d') : $p->date_permission;
                $pEnd = $p->end_date ? $p->end_date->format('Y-m-d') : ($p->start_date ? $p->start_date->format('Y-m-d') : $p->date_permission);
                return $date >= $pStart && $date <= $pEnd;
            });

            // Employee active on date?
            $isActiveOnDate = (! $user->joined_on || $date >= $user->joined_on->toDateString())
                && (! $user->left_on || $date <= $user->left_on->toDateString());

            // Determine status
            $statusKey = 'unassigned';
            $statusLabel = 'Di luar masa kerja';
            $badgeClass = 'muted';

            if (! $isActiveOnDate) {
                $statusKey = 'inactive_period';
                $statusLabel = 'Di luar masa kerja';
                $badgeClass = 'muted';
            } elseif ($att !== null) {
                $statusKey = 'present';
                $timing = $att->timing_label;
                $statusLabel = "Hadir ({$timing})";
                $badgeClass = ($timing === 'Tepat waktu') ? 'status-ontime' : (($timing === 'Terlambat') ? 'status-late' : 'status-review');
            } elseif ($isHoliday) {
                $statusKey = 'holiday';
                $statusLabel = $cal['label'] ?: 'Hari Libur';
                $badgeClass = 'status-off';
            } elseif (! $isWorking) {
                $statusKey = 'off';
                $statusLabel = $cal['label'] ?: 'Bukan hari kerja';
                $badgeClass = 'muted';
            } elseif ($perm !== null) {
                $statusKey = 'leave';
                $permStatus = $perm->status === 'cancellation_requested' ? 'Menunggu pembatalan' : 'Disetujui';
                $statusLabel = "Cuti / Izin ({$permStatus})";
                $badgeClass = 'status-leave';
            } elseif ($date > $today) {
                $statusKey = 'upcoming';
                $statusLabel = 'Terjadwal (mendatang)';
                $badgeClass = 'muted';
            } elseif ($date === $today) {
                $statusKey = 'today_pending';
                $statusLabel = 'Belum masuk / Belum terkonfirmasi';
                $badgeClass = 'status-review';
            } elseif ($isWorking) {
                $statusKey = 'absent';
                $statusLabel = 'Alpa / Tidak hadir';
                $badgeClass = 'status-absent';
            } else {
                $statusKey = 'off';
                $statusLabel = 'Bukan hari kerja';
                $badgeClass = 'muted';
            }

            // Calculate duration
            $workHours = null;
            if ($att && $att->checked_in_at && $att->checked_out_at) {
                $in = CarbonImmutable::parse($att->checked_in_at, 'UTC');
                $out = CarbonImmutable::parse($att->checked_out_at, 'UTC');
                if ($out->gt($in)) {
                    $workHours = round($in->diffInMinutes($out) / 60, 1);
                }
            } elseif ($att && $att->time_in && $att->time_out) {
                try {
                    $in = CarbonImmutable::parse("{$att->date} {$att->time_in}");
                    $out = CarbonImmutable::parse("{$att->date} {$att->time_out}");
                    if ($out->gt($in)) {
                        $workHours = round($in->diffInMinutes($out) / 60, 1);
                    }
                } catch (\Throwable) {}
            }

            // Schedule info
            $scheduleInfo = null;
            if ($att && $att->scheduled_name) {
                $scheduleInfo = $att->scheduled_name . ($att->scheduled_in ? " ({$att->scheduled_in}–{$att->scheduled_out})" : '');
            } elseif ($this->effectiveShift->ready()) {
                $sched = $this->effectiveShift->schedule($user, $date);
                if (! empty($sched['shift_name'])) {
                    $scheduleInfo = $sched['shift_name'] . (! empty($sched['time_in']) ? " ({$sched['time_in']}–{$sched['time_out']})" : '');
                }
            }
            if (! $scheduleInfo) {
                $scheduleInfo = $user->shift?->name ?: 'Tanpa jadwal tersimpan';
            }

            $days[] = [
                'date' => $date,
                'calendar' => $cal,
                'is_working' => $isWorking,
                'attendance' => $att,
                'permission' => $perm,
                'schedule_info' => $scheduleInfo,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'badge_class' => $badgeClass,
                'work_hours' => $workHours,
            ];
        }

        return [
            'days' => $days,
            'user' => $user,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'timezone' => $tz,
            'today' => $today,
        ];
    }

    private function dateRange(string $start, string $end): array
    {
        $dates = [];
        $cur = CarbonImmutable::parse($start);
        $last = CarbonImmutable::parse($end);
        while ($cur->lte($last)) {
            $dates[] = $cur->format('Y-m-d');
            $cur = $cur->addDay();
        }
        return $dates;
    }

    /**
     * Compute rows for a given collection of employees.
     */
    private function calculateRecapRows(
        Collection $employees,
        Company $company,
        string $startDate,
        string $endDate,
        array $scheduledDates,
        string $today
    ): array {
        if ($employees->isEmpty()) {
            return [];
        }

        $employeeIds = $employees->pluck('id')->all();

        // Attendances in date range
        $attendances = Attendance::whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        // Permissions in date range (approved or cancellation_requested)
        $approvalReady = Schema::hasColumn('permissions', 'status');
        $permissions = Permission::whereIn('user_id', $employeeIds)
            ->when($approvalReady, fn ($q) => $q->whereIn('status', ['approved', 'cancellation_requested']), fn ($q) => $q->where('is_approved', true))
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sub) use ($startDate, $endDate) {
                    $sub->whereNotNull('start_date')
                        ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                })->orWhere(function ($sub) use ($startDate, $endDate) {
                    $sub->whereNull('start_date')
                        ->whereBetween('date_permission', [$startDate, $endDate]);
                });
            })
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($employees as $employee) {
            $employeeScheduledDates = array_values(array_filter($scheduledDates, fn ($date) =>
                (! $employee->joined_on || $date >= $employee->joined_on->toDateString()) &&
                (! $employee->left_on || $date <= $employee->left_on->toDateString())
            ));

            $userAttendances = $attendances->get($employee->id, collect());
            $userPermissions = $permissions->get($employee->id, collect());

            $userAttendancesByDate = $userAttendances->keyBy('date');

            $presentDays = 0;
            $ontimeDays = 0;
            $lateDays = 0;
            $reviewDays = 0;
            $totalMinutes = 0;
            $attendedOnScheduledDays = 0;

            foreach ($userAttendancesByDate as $attDate => $att) {
                $presentDays++;
                if (in_array($attDate, $employeeScheduledDates, true)) {
                    $attendedOnScheduledDays++;
                }

                $label = $att->timing_label;
                if ($label === 'Tepat waktu') {
                    $ontimeDays++;
                } elseif ($label === 'Terlambat') {
                    $lateDays++;
                } else {
                    $reviewDays++;
                }

                if ($att->checked_in_at && $att->checked_out_at) {
                    $in = CarbonImmutable::parse($att->checked_in_at, 'UTC');
                    $out = CarbonImmutable::parse($att->checked_out_at, 'UTC');
                    if ($out->gt($in)) {
                        $totalMinutes += $in->diffInMinutes($out);
                    }
                } elseif ($att->time_in && $att->time_out) {
                    try {
                        $in = CarbonImmutable::parse("{$att->date} {$att->time_in}");
                        $out = CarbonImmutable::parse("{$att->date} {$att->time_out}");
                        if ($out->gt($in)) {
                            $totalMinutes += $in->diffInMinutes($out);
                        }
                    } catch (\Throwable) {}
                }
            }

            // Approved/Cancellation_requested leave days that fell on employee working days
            $leaveDates = [];
            foreach ($userPermissions as $perm) {
                $pStart = $perm->start_date ? $perm->start_date->format('Y-m-d') : $perm->date_permission;
                $pEnd = $perm->end_date ? $perm->end_date->format('Y-m-d') : ($perm->start_date ? $perm->start_date->format('Y-m-d') : $perm->date_permission);
                $pCur = CarbonImmutable::parse($pStart);
                $pEndDt = CarbonImmutable::parse($pEnd);
                while ($pCur->lte($pEndDt)) {
                    $dStr = $pCur->format('Y-m-d');
                    if (in_array($dStr, $employeeScheduledDates, true) && $dStr >= $startDate && $dStr <= $endDate) {
                        // Priority to attendance if employee also attended on that day
                        if (! $userAttendancesByDate->has($dStr)) {
                            $leaveDates[$dStr] = true;
                        }
                    }
                    $pCur = $pCur->addDay();
                }
            }

            $leaveWorkingDays = count($leaveDates);

            // Absent days calculation (PRD 4.B.5):
            // Only count elapsed past scheduled days (< today).
            // Future dates (> today) are never absent.
            // Today (= today) without attendance is unconfirmed, not final alpa.
            $pastAbsentDays = 0;
            $unconfirmedToday = 0;
            $futureScheduledDays = 0;

            foreach ($employeeScheduledDates as $schedDate) {
                $hasAttended = $userAttendancesByDate->has($schedDate);
                $hasLeave = isset($leaveDates[$schedDate]);

                if ($hasAttended || $hasLeave) {
                    continue;
                }

                if ($schedDate < $today) {
                    $pastAbsentDays++;
                } elseif ($schedDate === $today) {
                    $unconfirmedToday++;
                } else {
                    $futureScheduledDays++;
                }
            }

            $rows[] = [
                'user' => $employee,
                'scheduled_days' => count($employeeScheduledDates),
                'present_days' => $presentDays,
                'ontime_days' => $ontimeDays,
                'late_days' => $lateDays,
                'review_days' => $reviewDays,
                'leave_days' => $leaveWorkingDays,
                'absent_days' => $pastAbsentDays,
                'unconfirmed_days' => $unconfirmedToday,
                'future_scheduled_days' => $futureScheduledDays,
                'attended_scheduled_days' => $attendedOnScheduledDays,
                'total_work_hours' => round($totalMinutes / 60, 1),
            ];
        }

        return $rows;
    }

    /**
     * Compute summary statistics across all filtered employees using chunked processing.
     * Keeps memory bounded regardless of total employee population size.
     */
    public function calculateSummaryChunked(
        Builder $query,
        Company $company,
        string $startDate,
        string $endDate,
        array $scheduledDates,
        int $scheduledWorkingDaysCount,
        string $today,
        ?int $totalEmployees = null,
        int $chunkSize = 100
    ): array {
        if ($totalEmployees === null) {
            $totalEmployees = (clone $query)->count();
        }

        $totalPresentAll = 0;
        $totalOntimeAll = 0;
        $totalLateAll = 0;
        $totalReviewAll = 0;
        $totalLeaveAll = 0;
        $totalAbsentAll = 0;
        $totalCapacity = 0;
        $totalScheduledPresent = 0;

        (clone $query)->chunk($chunkSize, function (Collection $employeeChunk) use (
            $company,
            $startDate,
            $endDate,
            $scheduledDates,
            $today,
            &$totalPresentAll,
            &$totalOntimeAll,
            &$totalLateAll,
            &$totalReviewAll,
            &$totalLeaveAll,
            &$totalAbsentAll,
            &$totalCapacity,
            &$totalScheduledPresent
        ) {
            $rows = $this->calculateRecapRows(
                $employeeChunk,
                $company,
                $startDate,
                $endDate,
                $scheduledDates,
                $today
            );

            foreach ($rows as $row) {
                $totalPresentAll += $row['present_days'];
                $totalOntimeAll += $row['ontime_days'];
                $totalLateAll += $row['late_days'];
                $totalReviewAll += $row['review_days'];
                $totalLeaveAll += $row['leave_days'];
                $totalAbsentAll += $row['absent_days'];
                $totalCapacity += $row['scheduled_days'];
                $totalScheduledPresent += $row['attended_scheduled_days'];
            }
        });

        $attendanceRate = $totalCapacity > 0
            ? round(($totalScheduledPresent / $totalCapacity) * 100, 1)
            : 0;

        return [
            'total_employees' => $totalEmployees,
            'scheduled_working_days' => $scheduledWorkingDaysCount,
            'total_present' => $totalPresentAll,
            'total_ontime' => $totalOntimeAll,
            'total_late' => $totalLateAll,
            'total_review' => $totalReviewAll,
            'total_leave' => $totalLeaveAll,
            'total_absent' => $totalAbsentAll,
            'attendance_rate' => $attendanceRate,
            'scheduled_present' => $totalScheduledPresent,
            'scheduled_capacity' => $totalCapacity,
        ];
    }
}
