<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Services\EffectiveShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EffectiveShiftController extends Controller
{
    public function index(Request $request, EffectiveShift $service)
    {
        abort_unless($service->ready(), 503, 'Jadwal efektif menunggu migrasi.');
        $data = $request->validate(['user_id' => 'nullable|integer|min:1']);
        $users = User::where('company_id', $request->user()->company_id)->orderBy('name')->get();
        $selected = isset($data['user_id']) ? $users->firstWhere('id', (int) $data['user_id']) : $users->first();
        abort_if(isset($data['user_id']) && ! $selected, 404);
        $shifts = Shift::where('company_id', $request->user()->company_id)->orderBy('name')->get();
        $history = DB::table('employee_shift_assignments as e')->join('users as a', 'a.id', '=', 'e.actor_id')->where('e.company_id', $request->user()->company_id)->where('e.user_id', $selected?->id ?? 0)->orderByDesc('e.id')->select('e.*', 'a.name as actor_name')->paginate(15)->withQueryString();
        $expected = $selected ? (DB::table('employee_shift_assignments')->where('company_id', $request->user()->company_id)->where('user_id', $selected->id)->max('id') ?? 0) : 0;
        $today = now(app(\App\Services\WorkCalendar::class)->timezone($request->user()->company()->firstOrFail()))->toDateString();
        $schedule = $selected ? $service->schedule($selected, $today) : null;
        $assignmentsData = $selected ? DB::table('employee_shift_assignments')
            ->where('company_id', $request->user()->company_id)
            ->where('user_id', $selected->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['id', 'effective_from', 'shift_name', 'time_in', 'time_out', 'timezone']) : collect();
        $legacyShiftData = ($selected && $assignmentsData->isEmpty()) ? $selected->shift()->where('company_id', $request->user()->company_id)->first(['id', 'name', 'time_in', 'time_out']) : null;

        return view('pages.effective-shifts.index', compact('users', 'selected', 'shifts', 'history', 'expected', 'today', 'schedule', 'assignmentsData', 'legacyShiftData'));
    }

    public function store(Request $request, EffectiveShift $service)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|min:1',
            'shift_id' => 'nullable|integer|min:1',
            'effective_from' => 'required|date_format:Y-m-d',
            'expected_id' => 'required|integer|min:0',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $service->assign($request->user(), $data);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409 && ! $request->expectsJson()) {
                return back()->withInput()->with('conflict', $e->getMessage());
            }
            throw $e;
        }

        return redirect()->route('effective-shifts.index', ['user_id' => $data['user_id']])
            ->with('success', 'Jadwal efektif disimpan. Riwayat dan absensi yang sudah tercatat dipertahankan.');
    }
}
