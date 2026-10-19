<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QrAbsenController;
use App\Models\User;
use App\Models\Company;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\QrAbsen;
use App\Models\Note;
use App\Http\Controllers\VerifikasiPensiunController;
use App\Http\Controllers\OvertimeController;

Route::get('/', function () {
    return view('pages.auth.auth-login');
});

Route::middleware(['auth'])->group(function () {


Route::get('home', function () {
    $currentDate = date('Y-m-d');
    $user = auth()->user();
    
    if (in_array($user->role, ['admin', 'super admin', 'hrd', 'supervisor'])) {
        // Calculate late attendees
        $late_today = Attendance::where('date', $currentDate)
            ->whereHas('user', function ($query) {
                $query->whereHas('shift', function ($subQuery) {
                    // Ensure we compare against shift time
                });
            })
            ->get()
            ->filter(function ($attendance) {
                return $attendance->user && $attendance->user->shift && $attendance->time_in > $attendance->user->shift->time_in;
            })
            ->count();

        return view('pages.dashboard', [
            'type_menu' => 'home',
            'users_count' => User::count(),
            'shifts_count' => App\Models\Shift::count(),
            'present_today' => Attendance::where('date', $currentDate)->count(),
            'late_today' => $late_today,
            'latest_attendances' => Attendance::with('user')->orderBy('created_at', 'desc')->take(5)->get(),
            'latest_permissions' => Permission::with('user')->orderBy('created_at', 'desc')->take(5)->get(),
        ]);
    } else {
        // Calculate late attendees for the specific user
        $late_today = Attendance::where('date', $currentDate)
            ->where('user_id', $user->id)
            ->get()
            ->filter(function ($attendance) {
                return $attendance->user && $attendance->user->shift && $attendance->time_in > $attendance->user->shift->time_in;
            })
            ->count();

        return view('pages.dashboard', [
            'type_menu' => 'home',
            'users_count' => 0, // Not relevant for user dashboard
            'shifts_count' => 0, // Not relevant for user dashboard
            'present_today' => Attendance::where('date', $currentDate)->where('user_id', $user->id)->count(),
            'late_today' => $late_today,
            'latest_attendances' => Attendance::with('user')->where('user_id', $user->id)->orderBy('created_at', 'desc')->take(5)->get(),
            'latest_permissions' => Permission::with('user')->where('user_id', $user->id)->orderBy('created_at', 'desc')->take(5)->get(),
        ]);
    }
})->name('home');

    Route::get('features-profile.html', function () {
        return view('pages.profile', ['type_menu' => '']);
    })->name('profile');

    Route::resource('users', UserController::class);
    Route::resource('companies', CompanyController::class);
    Route::get('/attendances/export-csv', [AttendanceController::class, 'exportCsv'])->name('attendances.export-csv');
    Route::resource('attendances', AttendanceController::class);
    Route::resource('permissions', PermissionController::class);
    Route::resource('qr_absens', QrAbsenController::class);
    Route::get('/verifikasi/export-csv', [VerifikasiPensiunController::class, 'exportCsv'])->name('verifikasi.export-csv');
    Route::resource('verifikasi', VerifikasiPensiunController::class);
    Route::resource('reimbursements', App\Http\Controllers\ReimbursementController::class);
    Route::resource('shifts', App\Http\Controllers\ShiftController::class);
    Route::resource('overtimes', OvertimeController::class);

    Route::get('/qr-absens/{id}/download', [QrAbsenController::class, 'downloadPDF'])->name('qr_absens.download');
});
