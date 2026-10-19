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
            // Lock an existing row, even when this user has no attendance yet.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $company = $user->company()->firstOrFail();
            $now = now();
            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $now->toDateString())->lockForUpdate()->first();

            if ($checkout && !$attendance) {
                return response(['message' => 'Silakan check-in terlebih dahulu.'], 400);
            }
            if (!$checkout && $attendance) {
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
                if (!$valid) {
                    throw ValidationException::withMessages(['qr_code' => 'QR absensi tidak valid untuk hari ini. Silakan scan ulang.']);
                }
            } elseif ($mode !== 'none') {
                abort(409, 'Metode absensi perusahaan belum dikonfigurasi dengan benar.');
            }

            $coordinates = $data['latitude'].','.$data['longitude'];
            if ($checkout) {
                $attendance->update(['time_out' => $now->toTimeString(), 'latlon_out' => $coordinates]);
            } else {
                $attendance = Attendance::create([
                    'user_id' => $user->id, 'date' => $now->toDateString(),
                    'time_in' => $now->toTimeString(), 'latlon_in' => $coordinates,
                ]);
            }

            return response(['message' => $checkout ? 'Checkout success' : 'Checkin success', 'attendance' => $attendance], 200);
        }, 3);
    }

    public function isCheckedin(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->where('date', now()->toDateString())->first();

        return response(['checkedin' => $attendance !== null, 'checkedout' => $attendance?->time_out !== null], 200);
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->when($request->input('date'), fn ($query, $date) => $query->where('date', $date))->get();

        return response(['message' => 'Success', 'data' => $attendance], 200);
    }
}
