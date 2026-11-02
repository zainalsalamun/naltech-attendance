<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LeavePolicy
{
    public static function ready(): void
    {
        abort_unless(Schema::hasTable('leave_types') && Schema::hasTable('leave_type_events') && Schema::hasColumns('permissions', ['leave_type_id', 'leave_policy_snapshot']), 503, 'Kebijakan cuti menunggu penerapan migrasi oleh pengelola.');
    }

    public function save(User $actor, array $data, ?int $id = null): LeaveType
    {
        self::ready();
        try {
            return DB::transaction(function () use ($actor, $data, $id) {
                Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
                $actor = User::where('company_id', $actor->company_id)->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->isEmploymentActive() && in_array($actor->role, ['admin', 'super admin', 'hrd'], true), 403);
                $type = $id ? LeaveType::where('company_id', $actor->company_id)->whereKey($id)->lockForUpdate()->firstOrFail() : new LeaveType;
                abort_unless((int) ($type->version ?? 0) === (int) $data['version'], 409, 'Kebijakan berubah. Muat ulang sebelum menyimpan.');
                $before = $type->exists ? $type->policy() + ['is_active' => $type->is_active] : null;
                $type->forceFill(array_intersect_key($data, array_flip(['code', 'name', 'is_active', 'deducts_balance', 'annual_quota_days', 'requires_attachment', 'requirements'])));
                $type->forceFill(['company_id' => $actor->company_id, 'version' => (int) $data['version'] + 1])->save();
                DB::table('leave_type_events')->insert(['company_id' => $actor->company_id, 'leave_type_id' => $type->id, 'actor_id' => $actor->id, 'version' => $type->version, 'reason' => $data['change_reason'], 'snapshot' => json_encode(['before' => $before, 'after' => $type->policy() + ['is_active' => $type->is_active]]), 'created_at' => now()]);

                return $type;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['code' => 'Kode sudah digunakan oleh jenis cuti lain di perusahaan ini.']);
        }
    }

    public function submit(User $actor, array $data, ?UploadedFile $file): Permission
    {
        self::ready();
        $row = null;
        try {
            return DB::transaction(function () use ($actor, $data, $file, &$row) {
                $company = Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
                $actor = User::where('company_id', $actor->company_id)->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->isEmploymentActive(), 401);

                $startDate = $data['start_date'] ?? $data['date'] ?? null;
                $endDate = $data['end_date'] ?? $startDate;

                if (! $startDate || ! $endDate) {
                    throw ValidationException::withMessages(['date' => 'Tanggal awal dan akhir pengajuan wajib diisi.']);
                }

                $type = null;
                if (! empty($data['leave_type_id'])) {
                    $type = LeaveType::where('company_id', $actor->company_id)->whereKey($data['leave_type_id'])->lockForUpdate()->first();
                    if (! $type || ! $type->is_active) {
                        throw ValidationException::withMessages(['leave_type_id' => 'Pilih jenis cuti aktif dari perusahaan Anda.']);
                    }
                    if ($type->requires_attachment && ! $file) {
                        throw ValidationException::withMessages(['image' => 'Jenis cuti ini memerlukan lampiran bukti pendukung.']);
                    }
                }

                // Calculate working days & validate calendar completeness
                $calc = app(LeaveBalanceService::class)->calculateDays($company, $startDate, $endDate);

                if (! $calc['calendar_complete']) {
                    $missing = collect($calc['unconfigured_dates'])->map(fn ($d) => "{$d['date']} ({$d['label']})")->implode(', ');
                    throw ValidationException::withMessages([
                        'start_date' => "Kalender kerja perusahaan belum dikonfigurasi untuk tanggal: {$missing}. Hubungi HR untuk melengkapi kalender kerja sebelum mengajukan cuti pada tanggal ini.",
                    ]);
                }

                if ($calc['working_days_count'] === 0) {
                    $skippedDesc = collect($calc['skipped_days'])->map(fn ($d) => "{$d['date']}: {$d['label']}")->implode(', ');
                    throw ValidationException::withMessages([
                        'start_date' => "Seluruh tanggal dalam rentang yang dipilih merupakan hari libur ({$skippedDesc}). Pengajuan cuti tidak diperlukan untuk hari libur.",
                    ]);
                }

                // Check overlap with existing active submissions
                $overlap = Permission::where('user_id', $actor->id)
                    ->whereIn('status', ['pending', 'approved', 'cancellation_requested', 'needs_review'])
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
                    ->first();

                if ($overlap) {
                    $overlapLabel = $overlap->date_range_label ?? $overlap->date_permission;
                    throw ValidationException::withMessages([
                        'start_date' => "Anda sudah memiliki pengajuan aktif pada rentang tanggal tersebut (Pengajuan #{$overlap->id}: {$overlapLabel}, status: {$overlap->approval_label}). Pengajuan bertumpuk tidak diizinkan.",
                    ]);
                }

                // Check leave balance if leave type deducts balance
                $balance = null;
                $year = (int) \Carbon\CarbonImmutable::parse($startDate)->year;
                if ($type && $type->deducts_balance) {
                    if (LeaveBalanceService::ready()) {
                        $balance = LeaveBalance::where('company_id', $actor->company_id)
                            ->where('user_id', $actor->id)
                            ->where('leave_type_id', $type->id)
                            ->where('period_year', $year)
                            ->lockForUpdate()
                            ->first();

                        if (! $balance) {
                            throw ValidationException::withMessages([
                                'leave_type_id' => "Saldo cuti tahun {$year} untuk jenis '{$type->name}' belum dialokasikan oleh HR. Hubungi HR untuk alokasi kuota saldo cuti Anda.",
                            ]);
                        }

                        if ($balance->remaining_days < $calc['working_days_count']) {
                            throw ValidationException::withMessages([
                                'start_date' => "Sisa saldo cuti tidak mencukupi. Sisa saldo tersedia: {$balance->remaining_days} hari, durasi hari kerja yang diajukan: {$calc['working_days_count']} hari.",
                            ]);
                        }
                    }
                }

                $row = new Permission;
                $row->forceFill([
                    'leave_type_id' => $type?->id,
                    'leave_policy_snapshot' => $type?->policy(),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'duration_days' => $calc['working_days_count'],
                    'dates_breakdown' => $calc['dates_breakdown'],
                ]);

                $row = app(SubmissionAttachment::class)->save($row, [
                    'user_id' => $actor->id,
                    'date_permission' => $startDate,
                    'reason' => $data['reason'],
                    'is_approved' => false,
                ], $file, 'permissions');

                // Reserve balance if applicable
                if ($balance) {
                    app(LeaveBalanceService::class)->reserve(
                        $balance,
                        $row,
                        $actor,
                        "Cadangan pengajuan cuti #{$row->id} ({$startDate} s/d {$endDate}, {$calc['working_days_count']} hari kerja)"
                    );
                }

                return $row;
            });
        } catch (\Throwable $e) {
            if ($row?->image) {
                app(SubmissionAttachment::class)->delete($row->image);
            }
            throw $e;
        }
    }
}
