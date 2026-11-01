<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EffectiveShift
{
    public function ready(): bool
    {
        return Schema::hasTable('employee_shift_assignments') && Schema::hasColumn('attendances', 'checked_in_at');
    }

    public function assign(User $actor, array $data): void
    {
        abort_unless($this->ready(), 503, 'Jadwal efektif menunggu migrasi.');
        DB::transaction(function () use ($actor, $data) {
            $company = Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::where('company_id', $company->id)->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isEmploymentActive() && in_array($actor->role, ['admin', 'super admin', 'hrd'], true), 403);
            $owner = User::where('company_id', $company->id)->whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            abort_unless($owner->isEmploymentActive(), 422, 'Pilih karyawan aktif.');
            $zone = app(WorkCalendar::class)->timezone($company);
            $today = now($zone)->toDateString();
            if ($data['effective_from'] < $today || ($data['effective_from'] === $today && Attendance::where('user_id', $owner->id)->where('date', $today)->exists())) {
                throw ValidationException::withMessages(['effective_from' => 'Tanggal lampau atau tanggal yang sudah memiliki absensi tidak dapat diubah.']);
            }
            $latest = DB::table('employee_shift_assignments')->where('company_id', $company->id)->where('user_id', $owner->id)->max('id') ?? 0;
            abort_unless((int) $latest === (int) $data['expected_id'], 409, 'Jadwal karyawan telah diperbarui oleh pengguna lain saat formulir dibuka (konflik versi). Silakan tinjau perubahan terbaru sebelum menyimpan ulang.');
            $shift = empty($data['shift_id']) ? null : Shift::where('company_id', $company->id)->findOrFail($data['shift_id']);
            if (! $latest && $data['effective_from'] > $today) {
                $legacy = $owner->shift()->where('company_id', $company->id)->first();
                DB::table('employee_shift_assignments')->insert(['company_id' => $company->id, 'user_id' => $owner->id, 'shift_id' => $legacy?->id, 'effective_from' => $today, 'shift_name' => $legacy?->name, 'time_in' => $legacy?->time_in, 'time_out' => $legacy?->time_out, 'timezone' => $zone, 'actor_id' => $actor->id, 'reason' => 'Salinan penempatan saat penjadwalan efektif dimulai', 'created_at' => now()]);
            }
            DB::table('employee_shift_assignments')->insert(['company_id' => $company->id, 'user_id' => $owner->id, 'shift_id' => $shift?->id, 'effective_from' => $data['effective_from'], 'shift_name' => $shift?->name, 'time_in' => $shift?->time_in, 'time_out' => $shift?->time_out, 'timezone' => $zone, 'actor_id' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
        }, 3);
    }

    public function schedule(User $user, string $date): array
    {
        $assignment = $this->ready() ? DB::table('employee_shift_assignments')->where('company_id', $user->company_id)->where('user_id', $user->id)->where('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first() : null;
        $hasHistory = $this->ready() && DB::table('employee_shift_assignments')->where('company_id', $user->company_id)->where('user_id', $user->id)->exists();
        $shift = $assignment ? null : ($hasHistory ? null : $user->shift()->where('company_id', $user->company_id)->first());
        $zone = $assignment?->timezone ?? app(WorkCalendar::class)->timezone($user->company()->firstOrFail());
        $in = $assignment?->time_in ?? $shift?->time_in;
        $out = $assignment?->time_out ?? $shift?->time_out;
        $start = $in ? $this->instant($date, $in, $zone) : null;
        $end = $out ? $this->instant($out < $in ? CarbonImmutable::parse($date)->addDay()->toDateString() : $date, $out, $zone) : null;
        if ($in === $out) {
            $start = null;
            $end = null;
        }

        return ['schedule_assignment_id' => $assignment?->id, 'schedule_source' => $assignment ? 'effective' : ($shift ? 'legacy' : 'unassigned'), 'scheduled_name' => $assignment?->shift_name ?? $shift?->name, 'scheduled_in' => $in, 'scheduled_out' => $out, 'attendance_timezone' => $zone, 'scheduled_start_at' => $start?->utc()->format('Y-m-d H:i:s'), 'scheduled_end_at' => $end?->utc()->format('Y-m-d H:i:s')];
    }

    public function instant(string $date, string $time, string $zone): CarbonImmutable
    {
        $local = CarbonImmutable::parse($date.' '.$time, $zone);
        abort_unless($local->format('Y-m-d H:i:s') === $date.' '.substr($time.':00', 0, 8), 409, 'Jam shift tidak tersedia pada transisi zona waktu; hubungi HR.');
        $naive = CarbonImmutable::parse($date.' '.$time, 'UTC')->timestamp;
        $transitions = (new \DateTimeZone($zone))->getTransitions($naive - 172800, $naive + 172800);
        $offset = null;
        foreach ($transitions ?: [] as $transition) {
            if ($offset !== null && $transition['offset'] < $offset && $naive >= $transition['ts'] + $transition['offset'] && $naive < $transition['ts'] + $offset) {
                abort(409, 'Jam shift ambigu pada transisi zona waktu; hubungi HR.');
            }
            $offset = $transition['offset'];
        }

        return $local;
    }

    public function open(User $user): ?Attendance
    {
        if (! $this->ready()) {
            return null;
        }
        $rows = Attendance::where('user_id', $user->id)->whereNotNull('checked_in_at')->whereNull('time_out')->orderByDesc('id')->limit(2)->get();
        abort_if($rows->count() > 1, 409, 'Ada beberapa sesi terbuka. Hubungi HR.');

        return $rows->first();
    }

    public function expired(Attendance $row): bool
    {
        return $row->checked_in_at && CarbonImmutable::parse($row->checked_in_at, 'UTC')->addHours(24)->lessThan(CarbonImmutable::now('UTC'));
    }

    public function context(User $user): array
    {
        $now = CarbonImmutable::now(app(WorkCalendar::class)->timezone($user->company()->firstOrFail()));
        $date = $now->toDateString();
        $open = $this->open($user);
        if ($open) {
            return ['date' => $open->date, 'attendance' => $open, 'schedule' => array_intersect_key($open->getAttributes(), array_flip(['schedule_source', 'scheduled_name', 'scheduled_in', 'scheduled_out', 'attendance_timezone'])), 'actionable' => ! $this->expired($open)];
        }
        $previous = $this->schedule($user, $now->subDay()->toDateString());
        if ($previous['scheduled_in'] && $previous['scheduled_out'] < $previous['scheduled_in'] && $previous['scheduled_end_at'] && CarbonImmutable::parse($previous['scheduled_end_at'], 'UTC')->greaterThanOrEqualTo($now)) {
            $date = $now->subDay()->toDateString();
        }
        $row = Attendance::where('user_id', $user->id)->where('date', $date)->first();

        return ['date' => $date, 'attendance' => $row, 'schedule' => $row?->checked_in_at ? array_intersect_key($row->getAttributes(), array_flip(['schedule_source', 'scheduled_name', 'scheduled_in', 'scheduled_out', 'attendance_timezone'])) : $this->schedule($user, $date), 'actionable' => true];
    }

    public function snapshot(User $user, string $date): array
    {
        return $this->schedule($user, $date) + ['calendar_working' => app(WorkCalendar::class)->day($user->company()->firstOrFail(), $date)['working'], 'checked_in_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s')];
    }
}
