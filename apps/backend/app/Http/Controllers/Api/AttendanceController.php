<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\QrAbsen;
use App\Models\User;
use App\Services\AttendanceLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function checkin(Request $request, AttendanceLocation $location)
    {
        return $this->record($request, $location, false);
    }

    public function checkout(Request $request, AttendanceLocation $location)
    {
        return $this->record($request, $location, true);
    }

    private function record(Request $request, AttendanceLocation $location, bool $checkout)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        return DB::transaction(function () use ($request, $location, $data, $checkout) {
            // Match configuration/lifecycle lock order: company, then employee.
            \App\Models\Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            // Lock an existing row, even when this user has no attendance yet.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->isEmploymentActive(), 401, 'Akun nonaktif. Hubungi HR/admin.');
            $company = $user->company()->firstOrFail();
            $now = now(app(\App\Services\WorkCalendar::class)->timezone($company));
            $effective = app(\App\Services\EffectiveShift::class);
            $context = $effective->ready() ? $effective->context($user) : null;
            $date = $context['date'] ?? $now->toDateString();
            $attendance = Attendance::where('user_id', $user->id)->where('date', $date)->lockForUpdate()->first();
            if ($context && ! $context['actionable']) {
                abort(409, 'Sesi absensi melewati batas 24 jam. Minta HR mengoreksi sesi sebelum melanjutkan.');
            }
            if ($checkout && $attendance?->attendance_timezone) {
                $now = now($attendance->attendance_timezone);
            }

            if ($checkout && ! $attendance) {
                return response(['message' => 'Silakan check-in terlebih dahulu.'], 400);
            }
            if (! $checkout && $attendance) {
                return response(['message' => 'Anda sudah check-in hari ini.'], 409);
            }
            if ($checkout && $attendance->time_out !== null) {
                return response(['message' => 'Anda sudah check-out hari ini.'], 409);
            }

            $location->validate($company, (float) $data['latitude'], (float) $data['longitude']);
            $mode = strtolower(trim($company->attendance_type ?? 'None'));
            if ($mode === 'face') {
                // Client-side booleans and embeddings are not trusted identity proof.
                abort(503, 'Absensi wajah belum tersedia: verifikasi identitas di server belum dikonfigurasi. Hubungi admin untuk menggunakan QR atau mode manual.');
            }
            if ($mode === 'qr') {
                $request->validate(['qr_code' => 'required|string|max:255']);
                $column = $checkout ? 'qr_checkout' : 'qr_checkin';
                $valid = QrAbsen::where('date', $now->toDateString())
                    ->where($column, $request->string('qr_code')->toString())->exists();
                if (! $valid) {
                    throw ValidationException::withMessages(['qr_code' => 'QR absensi tidak valid untuk hari ini. Silakan scan ulang.']);
                }
            } elseif ($mode !== 'none') {
                abort(409, 'Metode absensi perusahaan belum dikonfigurasi dengan benar.');
            }

            $coordinates = $data['latitude'].','.$data['longitude'];
            if ($checkout) {
                $attendance->forceFill(['time_out' => $now->toTimeString(), 'latlon_out' => $coordinates] + ($effective->ready() ? ['checked_out_at' => \Carbon\CarbonImmutable::now('UTC')->format('Y-m-d H:i:s')] : []))->save();
            } else {
                $attendance = Attendance::create([
                    'user_id' => $user->id, 'date' => $date,
                    'time_in' => $now->toTimeString(), 'latlon_in' => $coordinates,
                ]);
                if ($effective->ready()) {
                    $attendance->forceFill($effective->snapshot($user, $date))->save();
                }
            }

            return response(['message' => $checkout ? 'Checkout success' : 'Checkin success', 'attendance' => $attendance], 200);
        }, 3);
    }

    public function isCheckedin(Request $request)
    {
        $effective = app(\App\Services\EffectiveShift::class);
        if ($effective->ready()) {
            $context = $effective->context($request->user());

            return response(['checkedin' => $context['attendance'] !== null, 'checkedout' => $context['attendance']?->time_out !== null, 'qr_date' => now(app(\App\Services\WorkCalendar::class)->timezone($request->user()->company()->firstOrFail()))->toDateString(), 'attendance_date' => $context['date'], 'attendance' => $context['attendance'], 'schedule' => $context['schedule'], 'actionable' => $context['actionable']]);
        }
        $attendance = Attendance::where('user_id', $request->user()->id)->where('date', now(app(\App\Services\WorkCalendar::class)->timezone($request->user()->company()->firstOrFail()))->toDateString())->first();

        return response(['checkedin' => $attendance !== null, 'checkedout' => $attendance?->time_out !== null]);
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->when($request->input('date'), fn ($query, $date) => $query->where('date', $date))->get();

        return response(['message' => 'Success', 'data' => $attendance], 200);
    }
}
