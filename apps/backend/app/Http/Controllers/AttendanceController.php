<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

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
        return Attendance::with(['user.shift' => fn ($query) => $query->where('company_id', auth()->user()->company_id)])
            ->when($filters['name'] !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'like', '%'.$filters['name'].'%')))
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
            fputcsv($handle, ['ID', 'Name', 'Date', 'Time In', 'Time Out', 'LatLong In', 'LatLong Out'], ',', '"', '');

            foreach ($attendances->lazy(500) as $attendance) {
                fputcsv($handle, array_map([\App\Support\CsvCell::class, 'text'], [
                    $attendance->id,
                    $attendance->user->name,
                    $attendance->date,
                    $attendance->time_in,
                    $attendance->time_out,
                    $attendance->latlon_in,
                    $attendance->latlon_out,
                ]), ',', '"', '');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    //edit
    public function edit($id)
    {
        $attendance = Attendance::findOrFail($id);

        return view('pages.absensi.edit', compact('attendance'));
    }

    //update
    public function update(Request $request, $id)
    {
        $request->validate([
            'time_in' => 'required',
            'time_out' => 'nullable',
            'latlon_in' => 'required',
            'latlon_out' => 'nullable',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update([
            'time_in' => $request->time_in,
            'time_out' => $request->time_out,
            'latlon_in' => $request->latlon_in,
            'latlon_out' => $request->latlon_out,
        ]);

        return redirect()->route('attendances.index')->with('success', 'Catatan kehadiran berhasil diperbarui.');
    }

    //destroy
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', 'Catatan kehadiran berhasil dihapus.');
    }
}
