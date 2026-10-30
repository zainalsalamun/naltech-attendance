<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\AttendanceRecap;
use App\Support\CsvCell;
use Illuminate\Http\Request;

class AttendanceRecapController extends Controller
{
    public function __construct(
        protected AttendanceRecap $recapService
    ) {}

    public function index(Request $request)
    {
        $company = $request->user()->company;
        $viewer = $request->user();

        $filters = $this->recapService->validateFilters($request, $company, $viewer);
        $data = $this->recapService->calculate($company, $filters, $viewer, 15, (int) $request->input('page', 1));

        $organizationUnits = OrganizationUnit::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedUser = null;
        if (! empty($filters['user_id'])) {
            $selectedUser = User::where('company_id', $company->id)->find($filters['user_id']);
        }

        return view('pages.attendance_recap.index', array_merge($data, [
            'filters' => $filters,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'nameFilter' => $filters['name'],
            'company' => $company,
            'organizationUnits' => $organizationUnits,
            'selectedUser' => $selectedUser,
        ]));
    }

    public function show(Request $request, $id)
    {
        $company = $request->user()->company;
        $viewer = $request->user();

        $user = User::where('company_id', $company->id)->findOrFail($id);

        if ($viewer->role === 'supervisor') {
            abort_unless((int) $user->manager_id === (int) $viewer->id, 403, 'Karyawan di luar cakupan bawahan Anda.');
        }

        $filters = $this->recapService->validateFilters($request, $company, $viewer);
        $data = $this->recapService->dailyBreakdown($company, $user, $filters['start_date'], $filters['end_date']);

        $returnParams = array_filter([
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'name' => $filters['name'] ?: null,
            'user_id' => $filters['user_id'] ?: null,
            'organization_unit_id' => $filters['organization_unit_id'] ?: null,
            'page' => $request->input('page') ?: null,
        ]);
        $returnUrl = route('attendance-recap.index', $returnParams);

        return view('pages.attendance_recap.show', array_merge($data, [
            'filters' => $filters,
            'company' => $company,
            'returnUrl' => $returnUrl,
        ]));
    }

    public function exportCsv(Request $request)
    {
        $company = $request->user()->company;
        $viewer = $request->user();

        $filters = $this->recapService->validateFilters($request, $company, $viewer);
        $filename = "rekap-kehadiran-{$filters['start_date']}-sd-{$filters['end_date']}.csv";

        $callback = function () use ($company, $filters, $viewer) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Nama Karyawan',
                'Peran',
                'Unit Organisasi Saat Ini',
                'Hari Kerja Terjadwal',
                'Tepat Waktu',
                'Terlambat',
                'Perlu Tinjau',
                'Total Hadir',
                'Cuti / Izin Efektif',
                'Alpa / Tidak Hadir',
                'Belum Hadir Hari Ini',
                'Total Jam Kerja',
            ], ',', '"', '');

            $this->recapService->streamRecapRows($company, $filters, $viewer, function ($row) use ($handle) {
                $user = $row['user'];
                fputcsv($handle, array_map([CsvCell::class, 'text'], [
                    $user->id,
                    $user->name,
                    $user->role,
                    $user->organizationUnit?->name ?: ($user->department ?: 'Belum diatur'),
                    $row['scheduled_days'],
                    $row['ontime_days'],
                    $row['late_days'],
                    $row['review_days'],
                    $row['present_days'],
                    $row['leave_days'],
                    $row['absent_days'],
                    $row['unconfirmed_days'] ?? 0,
                    number_format($row['total_work_hours'], 1, ',', '.').' jam',
                ]), ',', '"', '');
            }, 100);

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
