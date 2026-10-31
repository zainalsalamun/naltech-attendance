<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkCalendarController extends Controller
{
    private function authorizeWrite(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true), 403);
    }

    public function index(Request $request, WorkCalendar $calendar)
    {
        $company = $request->user()->company()->firstOrFail();
        $timezone = $calendar->timezone($company);
        $today = now($timezone)->toDateString();
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $input['month'] ?? now($timezone)->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
        $days = [];
        $dates = [];
        for ($d = $start; $d->month === $start->month; $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }
        $calendarDays = $calendar->days($company, $dates);
        foreach ($dates as $date) {
            $days[] = ['date' => $date, 'display' => CarbonImmutable::parse($date)->locale('id')->translatedFormat('l, d M Y')] + $calendarDays[$date];
        }
        $rules = DB::table('work_calendar_rules')->where('company_id', $company->id)->orderByDesc('effective_from')->paginate(10)->withQueryString();

        return view('pages.calendar.index', compact('company', 'timezone', 'today', 'month', 'days', 'rules'));
    }

    public function timezone(Request $request, WorkCalendar $calendar)
    {
        $this->authorizeWrite($request);
        $data = $request->validate(['timezone' => ['required', 'timezone:all']]);
        DB::transaction(function () use ($request, $calendar, $data) {
            $company = Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            if ($calendar->timezone($company) !== $data['timezone'] && ((app(\App\Services\EffectiveShift::class)->ready() && DB::table('employee_shift_assignments')->where('company_id', $company->id)->exists()) || DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')->where('users.company_id', $company->id)->exists())) {
                throw ValidationException::withMessages(['timezone' => 'Perusahaan sudah memiliki absensi. Perubahan zona waktu memerlukan migrasi riwayat terpisah; gunakan zona waktu yang sedang berlaku.']);
            }
            $company->forceFill($data)->save();
        });

        return back()->with('success', 'Zona waktu perusahaan disimpan.');
    }

    public function rule(Request $request, WorkCalendar $calendar)
    {
        $this->authorizeWrite($request);
        $data = $request->validate(['effective_from' => ['required', 'date_format:Y-m-d'], 'weekdays' => ['sometimes', 'array', 'max:7'], 'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'], 'confirm_calendar' => ['accepted']]);
        try {
            DB::transaction(function () use ($request, $calendar, $data) {
                $company = Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
                $this->futureDate($data['effective_from'], $company, $calendar, 'effective_from');
                DB::table('work_calendar_rules')->insert(['company_id' => $company->id, 'effective_from' => $data['effective_from'], 'weekdays' => json_encode(array_values(array_map('intval', $data['weekdays'] ?? []))), 'actor_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['effective_from' => 'Sudah ada aturan pada tanggal tersebut. Tambahkan aturan pada tanggal berikutnya.']);
        }

        return back()->with('success', 'Aturan hari kerja ditambahkan. Aturan tanggal sebelumnya dipertahankan.');
    }

    public function day(Request $request, WorkCalendar $calendar)
    {
        $this->authorizeWrite($request);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'is_working' => ['required', 'boolean'], 'label' => ['required', 'string', 'max:150']]);
        DB::transaction(function () use ($request, $calendar, $data) {
            $company = Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            $this->futureDate($data['date'], $company, $calendar, 'date');
            DB::table('work_calendar_days')->updateOrInsert(['company_id' => $company->id, 'date' => $data['date']], ['is_working' => $data['is_working'], 'label' => $data['label'], 'actor_id' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]);
        });

        return back()->with('success', 'Pengecualian tanggal disimpan.');
    }

    private function futureDate(string $date, Company $company, WorkCalendar $calendar, string $field): void
    {
        if ($date < now($calendar->timezone($company))->toDateString()) {
            throw ValidationException::withMessages([$field => 'Tanggal lampau tidak dapat diubah melalui konfigurasi kalender.']);
        }
    }
}
