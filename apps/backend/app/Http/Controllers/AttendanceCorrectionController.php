<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionEvent;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $companyId = $viewer->company_id;

        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $name = trim($filters['name'] ?? '');
        $date = $filters['date'] ?? '';

        $corrections = AttendanceCorrectionEvent::where('company_id', $companyId)
            ->with(['attendance.user', 'actor'])
            ->whereHas('attendance.user', function ($q) use ($viewer, $name) {
                $q->withoutGlobalScopes()
                    ->where('company_id', $viewer->company_id)
                    ->when($viewer->role === 'supervisor', fn ($sq) => $sq->where('manager_id', $viewer->id))
                    ->when($name !== '', fn ($sq) => $sq->where('name', 'like', "%{$name}%"));
            })
            ->when($date !== '', function ($query) use ($date) {
                $query->whereHas('attendance', fn ($q) => $q->where('date', $date));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.attendance_corrections.index', compact('corrections', 'filters', 'name', 'date'));
    }
}
