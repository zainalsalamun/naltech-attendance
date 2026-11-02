<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public static function ready(): bool
    {
        return Schema::hasTable('leave_balances')
            && Schema::hasTable('leave_balance_ledgers')
            && Schema::hasColumns('permissions', ['start_date', 'end_date', 'duration_days']);
    }

    public static function checkReady(): void
    {
        abort_unless(self::ready(), 503, 'Sistem saldo cuti dan rentang tanggal menunggu penerapan migrasi oleh pengelola.');
    }

    public function preview(User $user, array $data): array
    {
        $calc = $this->calculateDays($user->company()->firstOrFail(), $data['start_date'], $data['end_date']);
        $year = (int) CarbonImmutable::parse($data['start_date'])->year;
        $info = null;
        if (! empty($data['leave_type_id'])) {
            $type = LeaveType::where('company_id', $user->company_id)->where('is_active', true)->find($data['leave_type_id']);
            if (! $type) {
                throw ValidationException::withMessages(['leave_type_id' => 'Pilih jenis cuti aktif dari perusahaan Anda.']);
            }
            if ($type->deducts_balance) {
                self::checkReady();
                $balance = LeaveBalance::where('company_id', $user->company_id)->where('user_id', $user->id)->where('leave_type_id', $type->id)->where('period_year', $year)->first();
                $info = $balance ? [
                    'allocated_days' => $balance->allocated_days, 'reserved_days' => $balance->reserved_days,
                    'used_days' => $balance->used_days, 'remaining_days' => $balance->remaining_days,
                    'sufficient' => $balance->remaining_days >= $calc['working_days_count'],
                    'remaining_after' => max(0, $balance->remaining_days - $calc['working_days_count']),
                ] : ['not_allocated' => true, 'sufficient' => false];
                $info['period_year'] = $year;
            }
        }

        return ['calculation' => $calc, 'balance' => $info, 'period_year' => $year];
    }

    /**
     * Calculate working days and skipped days for a date range based on company work calendar.
     */
    public function calculateDays(Company $company, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tanggal akhir cuti tidak boleh mendahului tanggal awal cuti.',
            ]);
        }

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang cuti melintasi pergantian tahun kalender ('.$start->year.' ke '.$end->year.'). Silakan pisahkan menjadi dua pengajuan sesuai masing-masing tahun kalender untuk memastikan pencatatan saldo yang akurat.',
            ]);
        }

        $dates = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        if (count($dates) > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang pengajuan cuti maksimal 366 hari dalam satu pengajuan.',
            ]);
        }

        $calendarDays = app(WorkCalendar::class)->days($company, $dates);

        $workingDays = [];
        $skippedDays = [];
        $unconfiguredDates = [];

        foreach ($dates as $date) {
            $dayInfo = $calendarDays[$date] ?? ['working' => null, 'label' => 'Belum dikonfigurasi'];

            if ($dayInfo['working'] === null) {
                $unconfiguredDates[] = [
                    'date' => $date,
                    'label' => $dayInfo['label'],
                ];
            } elseif ($dayInfo['working'] === true) {
                $workingDays[] = [
                    'date' => $date,
                    'is_working' => true,
                    'label' => $dayInfo['label'] ?? 'Hari kerja',
                ];
            } else {
                $skippedDays[] = [
                    'date' => $date,
                    'is_working' => false,
                    'label' => $dayInfo['label'] ?? 'Hari libur',
                ];
            }
        }

        $allBreakdown = [];
        foreach ($dates as $date) {
            $dayInfo = $calendarDays[$date] ?? ['working' => null, 'label' => 'Belum dikonfigurasi'];
            $allBreakdown[] = [
                'date' => $date,
                'is_working' => $dayInfo['working'],
                'label' => $dayInfo['label'] ?? '',
            ];
        }

        return [
            'total_calendar_days' => count($dates),
            'working_days_count' => count($workingDays),
            'skipped_days_count' => count($skippedDays),
            'working_days' => $workingDays,
            'skipped_days' => $skippedDays,
            'dates_breakdown' => $allBreakdown,
            'unconfigured_dates' => $unconfiguredDates,
            'calendar_complete' => empty($unconfiguredDates),
        ];
    }

    /**
     * Allocate or adjust leave balance for an employee.
     */
    public function allocate(User $actor, User $targetUser, LeaveType $leaveType, int $year, int $allocatedDays, string $reason): LeaveBalance
    {
        self::checkReady();

        return DB::transaction(function () use ($actor, $targetUser, $leaveType, $year, $allocatedDays, $reason) {
            Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::where('company_id', $actor->company_id)->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            abort_unless($actor->isEmploymentActive() && in_array($actor->role, ['admin', 'super admin', 'hrd'], true), 403, 'Hanya HR/Admin yang berwenang mengatur alokasi saldo cuti.');
            abort_unless((int) $targetUser->company_id === (int) $actor->company_id, 403, 'Karyawan harus berada di perusahaan yang sama.');
            abort_unless((int) $leaveType->company_id === (int) $actor->company_id, 403, 'Jenis cuti harus berasal dari perusahaan yang sama.');
            abort_unless($leaveType->deducts_balance, 422, 'Jenis cuti ini tidak menggunakan saldo.');
            abort_unless($allocatedDays >= 0 && $allocatedDays <= 366, 422, 'Alokasi saldo harus di antara 0 dan 366 hari.');
            abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 1000, 422, 'Alasan penyesuaian/alokasi saldo wajib diisi maksimal 1.000 karakter.');

            $balance = LeaveBalance::where('company_id', $actor->company_id)
                ->where('user_id', $targetUser->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('period_year', $year)
                ->lockForUpdate()
                ->first();

            $isNew = false;
            if (! $balance) {
                $isNew = true;
                $balance = new LeaveBalance([
                    'company_id' => $actor->company_id,
                    'user_id' => $targetUser->id,
                    'leave_type_id' => $leaveType->id,
                    'period_year' => $year,
                    'allocated_days' => 0,
                    'reserved_days' => 0,
                    'used_days' => 0,
                    'remaining_days' => 0,
                ]);
            }

            $prevAllocated = (int) $balance->allocated_days;
            $prevReserved = (int) $balance->reserved_days;
            $prevUsed = (int) $balance->used_days;
            $prevRemaining = (int) $balance->remaining_days;

            // Calculate new remaining days
            $newRemaining = $allocatedDays - $prevReserved - $prevUsed;
            if ($newRemaining < 0) {
                throw ValidationException::withMessages([
                    'allocated_days' => "Alokasi baru ({$allocatedDays} hari) tidak boleh lebih kecil dari kuota yang sudah dicadangkan/terpakai ({$prevReserved} cadangan + {$prevUsed} terpakai = ".($prevReserved + $prevUsed).' hari).',
                ]);
            }

            $balance->allocated_days = $allocatedDays;
            $balance->remaining_days = $newRemaining;
            $balance->save();

            $transactionType = $isNew ? 'allocation' : 'adjustment';
            $amountDays = $allocatedDays - $prevAllocated;

            DB::table('leave_balance_ledgers')->insert([
                'company_id' => $actor->company_id,
                'leave_balance_id' => $balance->id,
                'user_id' => $targetUser->id,
                'actor_id' => $actor->id,
                'permission_id' => null,
                'transaction_type' => $transactionType,
                'amount_days' => $amountDays,
                'previous_allocated' => $prevAllocated,
                'previous_reserved' => $prevReserved,
                'previous_used' => $prevUsed,
                'previous_remaining' => $prevRemaining,
                'current_allocated' => $balance->allocated_days,
                'current_reserved' => $balance->reserved_days,
                'current_used' => $balance->used_days,
                'current_remaining' => $balance->remaining_days,
                'reason' => trim($reason),
                'created_at' => now(),
            ]);

            return $balance;
        }, 3);
    }

    /**
     * Reserve balance when leave submission is created.
     */
    public function reserve(LeaveBalance $balance, Permission $permission, User $actor, string $reason): LeaveBalance
    {
        $duration = (int) $permission->duration_days;
        if ($duration <= 0) {
            return $balance;
        }

        $prevAllocated = (int) $balance->allocated_days;
        $prevReserved = (int) $balance->reserved_days;
        $prevUsed = (int) $balance->used_days;
        $prevRemaining = (int) $balance->remaining_days;

        if ($prevRemaining < $duration) {
            throw ValidationException::withMessages([
                'start_date' => "Saldo cuti tidak mencukupi. Sisa saldo tersedia: {$prevRemaining} hari, durasi yang diajukan: {$duration} hari.",
            ]);
        }

        $balance->reserved_days = $prevReserved + $duration;
        $balance->remaining_days = $prevRemaining - $duration;
        $balance->save();

        DB::table('leave_balance_ledgers')->insert([
            'company_id' => $balance->company_id,
            'leave_balance_id' => $balance->id,
            'user_id' => $balance->user_id,
            'actor_id' => $actor->id,
            'permission_id' => $permission->id,
            'transaction_type' => 'reserve',
            'amount_days' => -$duration,
            'previous_allocated' => $prevAllocated,
            'previous_reserved' => $prevReserved,
            'previous_used' => $prevUsed,
            'previous_remaining' => $prevRemaining,
            'current_allocated' => $balance->allocated_days,
            'current_reserved' => $balance->reserved_days,
            'current_used' => $balance->used_days,
            'current_remaining' => $balance->remaining_days,
            'reason' => trim($reason),
            'created_at' => now(),
        ]);

        return $balance;
    }

    /**
     * Move reserved days to used days when final approval is granted.
     */
    public function useReserved(Permission $permission, User $actor, string $reason): ?LeaveBalance
    {
        if (! self::ready() || ! $permission->leave_type_id) {
            return null;
        }

        $duration = (int) $permission->duration_days;
        if ($duration <= 0) {
            return null;
        }

        $startDate = $permission->start_date ?: $permission->date_permission;
        $year = (int) CarbonImmutable::parse($startDate)->year;

        $balance = LeaveBalance::where('company_id', $permission->user->company_id)
            ->where('user_id', $permission->user_id)
            ->where('leave_type_id', $permission->leave_type_id)
            ->where('period_year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            return null;
        }

        $prevAllocated = (int) $balance->allocated_days;
        $prevReserved = (int) $balance->reserved_days;
        $prevUsed = (int) $balance->used_days;
        $prevRemaining = (int) $balance->remaining_days;

        // Decrease reserved, increase used. Remaining was already subtracted during reservation.
        $newReserved = max(0, $prevReserved - $duration);
        $newUsed = $prevUsed + $duration;

        $balance->reserved_days = $newReserved;
        $balance->used_days = $newUsed;
        $balance->save();

        DB::table('leave_balance_ledgers')->insert([
            'company_id' => $balance->company_id,
            'leave_balance_id' => $balance->id,
            'user_id' => $balance->user_id,
            'actor_id' => $actor->id,
            'permission_id' => $permission->id,
            'transaction_type' => 'use',
            'amount_days' => $duration,
            'previous_allocated' => $prevAllocated,
            'previous_reserved' => $prevReserved,
            'previous_used' => $prevUsed,
            'previous_remaining' => $prevRemaining,
            'current_allocated' => $balance->allocated_days,
            'current_reserved' => $balance->reserved_days,
            'current_used' => $balance->used_days,
            'current_remaining' => $balance->remaining_days,
            'reason' => trim($reason),
            'created_at' => now(),
        ]);

        return $balance;
    }

    /**
     * Release reserved days back to remaining when rejected or cancelled before final decision.
     */
    public function releaseReserved(Permission $permission, User $actor, string $reason): ?LeaveBalance
    {
        if (! self::ready() || ! $permission->leave_type_id) {
            return null;
        }

        $duration = (int) $permission->duration_days;
        if ($duration <= 0) {
            return null;
        }

        $startDate = $permission->start_date ?: $permission->date_permission;
        $year = (int) CarbonImmutable::parse($startDate)->year;

        $balance = LeaveBalance::where('company_id', $permission->user->company_id)
            ->where('user_id', $permission->user_id)
            ->where('leave_type_id', $permission->leave_type_id)
            ->where('period_year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            return null;
        }

        $prevAllocated = (int) $balance->allocated_days;
        $prevReserved = (int) $balance->reserved_days;
        $prevUsed = (int) $balance->used_days;
        $prevRemaining = (int) $balance->remaining_days;

        $newReserved = max(0, $prevReserved - $duration);
        $newRemaining = $prevRemaining + $duration;

        $balance->reserved_days = $newReserved;
        $balance->remaining_days = $newRemaining;
        $balance->save();

        DB::table('leave_balance_ledgers')->insert([
            'company_id' => $balance->company_id,
            'leave_balance_id' => $balance->id,
            'user_id' => $balance->user_id,
            'actor_id' => $actor->id,
            'permission_id' => $permission->id,
            'transaction_type' => 'release_reserved',
            'amount_days' => $duration,
            'previous_allocated' => $prevAllocated,
            'previous_reserved' => $prevReserved,
            'previous_used' => $prevUsed,
            'previous_remaining' => $prevRemaining,
            'current_allocated' => $balance->allocated_days,
            'current_reserved' => $balance->reserved_days,
            'current_used' => $balance->used_days,
            'current_remaining' => $balance->remaining_days,
            'reason' => trim($reason),
            'created_at' => now(),
        ]);

        return $balance;
    }

    /**
     * Restore used days back to remaining when approved leave cancellation is approved by HR.
     */
    public function restoreUsed(Permission $permission, User $actor, string $reason): ?LeaveBalance
    {
        if (! self::ready() || ! $permission->leave_type_id) {
            return null;
        }

        $duration = (int) $permission->duration_days;
        if ($duration <= 0) {
            return null;
        }

        $startDate = $permission->start_date ?: $permission->date_permission;
        $year = (int) CarbonImmutable::parse($startDate)->year;

        $balance = LeaveBalance::where('company_id', $permission->user->company_id)
            ->where('user_id', $permission->user_id)
            ->where('leave_type_id', $permission->leave_type_id)
            ->where('period_year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            return null;
        }

        $prevAllocated = (int) $balance->allocated_days;
        $prevReserved = (int) $balance->reserved_days;
        $prevUsed = (int) $balance->used_days;
        $prevRemaining = (int) $balance->remaining_days;

        $newUsed = max(0, $prevUsed - $duration);
        $newRemaining = $prevRemaining + $duration;

        $balance->used_days = $newUsed;
        $balance->remaining_days = $newRemaining;
        $balance->save();

        DB::table('leave_balance_ledgers')->insert([
            'company_id' => $balance->company_id,
            'leave_balance_id' => $balance->id,
            'user_id' => $balance->user_id,
            'actor_id' => $actor->id,
            'permission_id' => $permission->id,
            'transaction_type' => 'restore_used',
            'amount_days' => $duration,
            'previous_allocated' => $prevAllocated,
            'previous_reserved' => $prevReserved,
            'previous_used' => $prevUsed,
            'previous_remaining' => $prevRemaining,
            'current_allocated' => $balance->allocated_days,
            'current_reserved' => $balance->reserved_days,
            'current_used' => $balance->used_days,
            'current_remaining' => $balance->remaining_days,
            'reason' => trim($reason),
            'created_at' => now(),
        ]);

        return $balance;
    }
}
