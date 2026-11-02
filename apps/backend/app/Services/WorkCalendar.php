<?php

namespace App\Services;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkCalendar
{
    public function ready(): bool
    {
        return Schema::hasColumn('companies', 'timezone') && Schema::hasTable('work_calendar_rules') && Schema::hasTable('work_calendar_days');
    }

    public function timezone(Company $company): string
    {
        return $company->timezone ?: config('app.timezone');
    }

    public function day(Company $company, string $date): array
    {
        return $this->days($company, [$date])[$date];
    }

    public function days(Company $company, array $dates): array
    {
        if ($dates === []) {
            return [];
        }
        $ready = $this->ready();
        $overrides = $ready ? DB::table('work_calendar_days')->where('company_id', $company->id)->whereIn('date', $dates)->get()->keyBy('date') : collect();
        $rules = $ready ? DB::table('work_calendar_rules')->where('company_id', $company->id)->where('effective_from', '<=', max($dates))->orderByDesc('effective_from')->get() : collect();
        $result = [];
        foreach ($dates as $date) {
            $override = $overrides->get($date);
            $rule = $rules->first(fn ($row) => $row->effective_from <= $date);
            if ($override) {
                $result[$date] = ['working' => (bool) $override->is_working, 'label' => $override->label];
            } elseif ($rule) {
                $working = in_array(CarbonImmutable::parse($date)->dayOfWeekIso, json_decode($rule->weekdays, true), true);
                $result[$date] = ['working' => $working, 'label' => $working ? 'Hari kerja mingguan' : 'Hari libur mingguan'];
            } else {
                $result[$date] = ['working' => null, 'label' => $ready ? 'Hari kerja belum dikonfigurasi untuk tanggal ini' : 'Kalender belum diterapkan'];
            }
        }

        return $result;
    }
}
