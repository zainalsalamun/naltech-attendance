<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\User;
use App\Services\EffectiveShift;
use App\Support\CsvCell;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    //index
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $attendances = $this->filteredAttendances($filters)->orderByDesc('date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('pages.absensi.index', compact('attendances', 'filters'));
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? ''];
    }

    private function filteredAttendances(array $filters)
    {
        $viewer = auth()->user();
        return Attendance::with(['user.shift' => fn ($query) => $query->where('company_id', $viewer->company_id)])
            ->whereHas('user', function ($users) use ($filters, $viewer) {
                $users->withoutGlobalScopes()
                    ->where('company_id', $viewer->company_id)
                    ->when($viewer->role === 'supervisor', fn ($q) => $q->where('manager_id', $viewer->id))
                    ->when($filters['name'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['name'].'%'));
            })
            ->when($filters['date'] !== '', fn ($query) => $query->where('date', $filters['date']));
    }

    public function exportCsv(Request $request)
    {
        $attendances = $this->filteredAttendances($this->filters($request))->orderByDesc('date')->orderByDesc('id');
        $companyId = (int) $request->user()->company_id;
        $attendances->whereHas('user', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId));

        $filename = 'attendances.csv';

        $callback = function () use ($attendances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Date', 'Time In', 'Time Out', 'LatLong In', 'LatLong Out', 'Schedule', 'Scheduled In', 'Scheduled Out', 'Timezone', 'Checkin UTC', 'Checkout UTC'], ',', '"', '');

            foreach ($attendances->lazy(500) as $attendance) {
                fputcsv($handle, array_map([CsvCell::class, 'text'], [
                    $attendance->id,
                    $attendance->user->name,
                    $attendance->date,
                    $attendance->time_in,
                    $attendance->time_out,
                    $attendance->latlon_in,
                    $attendance->latlon_out,
                    $attendance->scheduled_name,
                    $attendance->scheduled_in,
                    $attendance->scheduled_out,
                    $attendance->attendance_timezone,
                    $attendance->checked_in_at,
                    $attendance->checked_out_at,
                ]), ',', '"', '');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    //edit
    public function edit($id)
    {
        $viewer = auth()->user();
        $companyId = $viewer->company_id;

        $attendance = Attendance::withoutGlobalScopes()
            ->with(['correctionEvents.actor', 'user' => fn ($q) => $q->withoutGlobalScopes()])
            ->findOrFail($id);

        abort_unless((int) $attendance->user?->company_id === (int) $companyId, 404);

        if ($viewer->role === 'supervisor') {
            abort_unless((int) $attendance->user?->manager_id === (int) $viewer->id, 403, 'Karyawan di luar cakupan bawahan Anda.');
        }

        return view('pages.absensi.edit', compact('attendance'));
    }

    //update
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'time_in' => ['required', 'date_format:H:i,H:i:s'],
            'time_out' => ['nullable', 'date_format:H:i,H:i:s'],
            'checkin_date' => ['nullable', 'date_format:Y-m-d'],
            'checkout_date' => ['nullable', 'required_with:time_out', 'date_format:Y-m-d'],
            'latlon_in' => ['required', 'string', 'max:255'],
            'latlon_out' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'version' => ['nullable', 'integer'],
            'concurrency_token' => ['nullable', 'string'],
        ]);

        $hasReasonField = $request->has('correction_reason');
        $rawReason = $request->input('correction_reason');
        if ($hasReasonField) {
            $reason = trim((string) $rawReason);
            if ($reason === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'correction_reason' => 'Alasan koreksi wajib diisi.',
                ]);
            }
        } else {
            $reason = 'Koreksi catatan kehadiran';
        }

        $expectedVersion = isset($data['version']) ? (int) $data['version'] : null;
        $concurrencyToken = $data['concurrency_token'] ?? null;

        $redirectResponse = DB::transaction(function () use ($request, $id, $data, $reason, $expectedVersion, $concurrencyToken) {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            abort_unless($actor->isEmploymentActive(), 403, 'Akun pengelola tidak aktif.');

            $row = Attendance::with('user')->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->user->company_id === (int) $actor->company_id, 403, 'Perusahaan tidak sesuai.');

            abort_unless(
                in_array($actor->role, ['admin', 'super admin', 'hrd'], true) ||
                ($actor->role === 'supervisor' && (int) $row->user->manager_id === (int) $actor->id),
                403,
                'Anda tidak berwenang mengoreksi catatan kehadiran karyawan ini.'
            );

            // Concurrency control: prevent silent lost updates
            $hasVersionCol = Schema::hasColumn('attendances', 'version');
            if ($hasVersionCol && $expectedVersion !== null) {
                if ((int) $row->version !== $expectedVersion) {
                    abort(409, 'Catatan kehadiran telah diperbarui oleh pengelola lain. Silakan muat ulang halaman untuk meninjau perubahan terbaru.');
                }
            } elseif ($concurrencyToken !== null && $row->updated_at) {
                if ((string) $row->updated_at->timestamp !== (string) $concurrencyToken) {
                    abort(409, 'Catatan kehadiran telah diperbarui oleh pengelola lain. Silakan muat ulang halaman untuk meninjau perubahan terbaru.');
                }
            }

            $tz = $row->attendance_timezone ?: ($actor->company?->timezone ?: config('app.timezone'));
            $checkinDate = ! empty($data['checkin_date']) ? $data['checkin_date'] : $row->date;
            $checkoutDate = ! empty($data['checkout_date']) ? $data['checkout_date'] : $checkinDate;

            $in = app(EffectiveShift::class)->instant($checkinDate, $data['time_in'], $tz);
            $out = ! empty($data['time_out']) ? app(EffectiveShift::class)->instant($checkoutDate, $data['time_out'], $tz) : null;

            abort_unless(
                in_array($in->toDateString(), [$row->date, CarbonImmutable::parse($row->date)->addDay()->toDateString()], true)
                && (! $out || ($out >= $in && $out <= $in->addHours(24))),
                422,
                'Periksa tanggal/jam koreksi. Durasi maksimal 24 jam.'
            );

            $newTimeIn = $in->toTimeString();
            $newTimeOut = $out?->toTimeString();
            $newUtcIn = $in->utc()->format('Y-m-d H:i:s');
            $newUtcOut = $out?->utc()->format('Y-m-d H:i:s');
            $newLatlonIn = (string) $data['latlon_in'];
            $newLatlonOut = ! empty($data['latlon_out']) ? (string) $data['latlon_out'] : null;

            // No-op detection: do not create fake audit event if effective data is identical
            $isNoOp = ($row->time_in === $newTimeIn)
                && ($row->time_out === $newTimeOut)
                && ($row->checked_in_at === $newUtcIn)
                && ($row->checked_out_at === $newUtcOut)
                && ((string) $row->latlon_in === $newLatlonIn)
                && (($row->latlon_out !== null ? (string) $row->latlon_out : null) === $newLatlonOut);

            if ($isNoOp) {
                return redirect()->route('attendances.index')->with('info', 'Tidak ada perubahan pada catatan kehadiran; riwayat audit tidak ditambahkan.');
            }

            $before = $row->only(['checked_in_at', 'checked_out_at', 'time_in', 'time_out', 'latlon_in', 'latlon_out']);

            $updatePayload = [
                'time_in' => $newTimeIn,
                'time_out' => $newTimeOut,
                'checked_in_at' => $newUtcIn,
                'checked_out_at' => $newUtcOut,
                'latlon_in' => $newLatlonIn,
                'latlon_out' => $newLatlonOut,
                'attendance_timezone' => $tz,
            ];

            if ($hasVersionCol) {
                $updatePayload['version'] = ((int) $row->version) + 1;
            }

            $row->forceFill($updatePayload)->save();

            DB::table('attendance_correction_events')->insert([
                'company_id' => $actor->company_id,
                'attendance_id' => $row->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'snapshot' => json_encode([
                    'before' => $before,
                    'after' => $row->only(['checked_in_at', 'checked_out_at', 'time_in', 'time_out', 'latlon_in', 'latlon_out']),
                ]),
                'created_at' => now(),
            ]);

            return redirect()->route('attendances.index')->with('success', 'Koreksi tercatat dengan alasan; salinan jadwal dipertahankan.');
        });

        return $redirectResponse;
    }

    //destroy
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        abort_if($attendance->scheduled_start_at, 409, 'Catatan bersalinan jadwal dipertahankan. Gunakan koreksi kehadiran.');
        $attendance->correctionEvents()->delete();
        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', 'Catatan kehadiran berhasil dihapus.');
    }
}
