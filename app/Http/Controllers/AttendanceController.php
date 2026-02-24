<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    //index
    public function index(Request $request)
    {
        $attendances = Attendance::with('user')
            ->when($request->input('name'), function ($query, $name) {
                $query->whereHas('user', function ($query) use ($name) {
                    $query->where('name', 'like', '%' . $name . '%');
                });
            })->orderBy('id', 'desc')->paginate(10);
        return view('pages.absensi.index', compact('attendances'));
    }

    //export csv
    public function exportCsv(Request $request)
    {
        $attendances = Attendance::with('user')
            ->when($request->input('name'), function ($query, $name) {
                $query->whereHas('user', function ($query) use ($name) {
                    $query->where('name', 'like', '%' . $name . '%');
                });
            })->orderBy('id', 'desc')->get();

        $filename = 'attendances.csv';

        $callback = function () use ($attendances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Date', 'Time In', 'Time Out', 'LatLong In', 'LatLong Out']);

            foreach ($attendances as $attendance) {
                fputcsv($handle, [
                    $attendance->id,
                    $attendance->user->name,
                    $attendance->date,
                    $attendance->time_in,
                    $attendance->time_out,
                    $attendance->latlon_in,
                    $attendance->latlon_out,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
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

        return redirect()->route('attendances.index')->with('success', 'Attendance updated successfully');
    }

    //destroy
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', 'Attendance deleted successfully');
    }
}
