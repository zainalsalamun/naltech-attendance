<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class VerifikasiPensiunController extends Controller
{
    private function filters(Request $request): array
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:100'], 'date' => ['nullable', 'date_format:Y-m-d']]);

        return ['name' => trim($data['name'] ?? ''), 'date' => $data['date'] ?? ''];
    }

    private function records(array $filters)
    {
        return Attendance::with(['user.shift' => fn ($query) => $query->where('company_id', auth()->user()->company_id)])
            ->when($filters['name'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->orderByDesc('date')->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $attendances = $this->records($filters)->paginate(10)->withQueryString();

        return view('pages.verifikasi.index', compact('attendances', 'filters'));
    }

    private function csvText(?string $value): string
    {
        $value = $value ?? '';

        return preg_match('/^(\s*[=+\-@]|[\t\r])/u', $value) ? "'".$value : $value;
    }

    public function exportCsv(Request $request)
    {
        $records = $this->records($this->filters($request));

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nama', 'Tanggal', 'Jam masuk', 'Jam pulang', 'Koordinat masuk', 'Koordinat pulang'], ',', '"', '');
            foreach ($records->lazy(500) as $record) {
                fputcsv($handle, [$record->id, $this->csvText($record->user->name), $record->date, $record->time_in, $record->time_out,
                    $this->csvText($record->latlon_in), $this->csvText($record->latlon_out)], ',', '"', '');
            }
            fclose($handle);
        }, 'verifikasi_pensiun.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
