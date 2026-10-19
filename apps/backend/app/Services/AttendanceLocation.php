<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class AttendanceLocation
{
    public function validate(Company $company, float $latitude, float $longitude): void
    {
        abort_unless(is_numeric($company->latitude) && is_numeric($company->longitude)
            && is_numeric($company->radius_km) && abs((float) $company->latitude) <= 90
            && abs((float) $company->longitude) <= 180 && (float) $company->radius_km > 0,
            409, 'Lokasi atau radius perusahaan belum dikonfigurasi dengan benar.');

        $lat1 = deg2rad((float) $company->latitude);
        $lat2 = deg2rad($latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($longitude - (float) $company->longitude);
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $distance = 6371.0088 * 2 * asin(sqrt(min(1, max(0, $a))));

        if ($distance > (float) $company->radius_km) {
            throw ValidationException::withMessages([
                'latitude' => 'Anda berada di luar radius absensi perusahaan.',
            ]);
        }
    }
}
