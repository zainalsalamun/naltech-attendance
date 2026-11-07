<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QrAbsenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifikasiPensiunController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'home' : 'login');
});

Route::middleware(['auth', \App\Http\Middleware\EnsureCompany::class])->group(function () {

    Route::post('submission-cancellations/{kind}/{id}', [\App\Http\Controllers\SubmissionApprovalController::class, 'cancel'])->where('kind', 'permission|overtime|reimbursement')->whereNumber('id')->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class)->name('submission-approvals.cancel');
    Route::get('home', \App\Http\Controllers\DashboardController::class)->name('home');
    Route::get('/submission-attachments/{kind}/{id}', [App\Http\Controllers\SubmissionAttachmentController::class, 'show'])
        ->where('kind', 'permission|reimbursement')->whereNumber('id')->name('submission-attachments.show');
    Route::post('leave-calculate', [\App\Http\Controllers\LeaveBalanceController::class, 'calculate'])->name('leave-balances.calculate');

    Route::get('features-profile.html', function () {
        return view('pages.profile', ['profileCompany' => auth()->user()->company()->firstOrFail()]);
    })->name('profile');

    Route::middleware(\App\Http\Middleware\EnsureAttendanceManager::class)->group(function () {
        Route::get('leave-balances', [\App\Http\Controllers\LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::post('leave-balances', [\App\Http\Controllers\LeaveBalanceController::class, 'store'])->name('leave-balances.store');
        Route::post('leave-balances/batch', [\App\Http\Controllers\LeaveBalanceController::class, 'batchAllocate'])->name('leave-balances.batch');
        Route::get('leave-balances/ledger', [\App\Http\Controllers\LeaveBalanceController::class, 'ledger'])->name('leave-balances.ledger');
        Route::get('effective-shifts', [\App\Http\Controllers\EffectiveShiftController::class, 'index'])->name('effective-shifts.index');
        Route::post('effective-shifts', [\App\Http\Controllers\EffectiveShiftController::class, 'store'])->name('effective-shifts.store');
        Route::resource('leave-types', \App\Http\Controllers\LeaveTypeController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware(\App\Http\Middleware\EnsureLeavePolicyReady::class);
        Route::get('approval-workflows', [\App\Http\Controllers\ApprovalWorkflowController::class, 'index'])->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class)->name('approval-workflows.index');
        Route::post('approval-workflows', [\App\Http\Controllers\ApprovalWorkflowController::class, 'store'])->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class)->name('approval-workflows.store');
        Route::middleware(\App\Http\Middleware\EnsureWorkCalendarReady::class)->group(function () {
            Route::get('work-calendar', [\App\Http\Controllers\WorkCalendarController::class, 'index'])->name('work-calendar.index');
            Route::put('work-calendar/timezone', [\App\Http\Controllers\WorkCalendarController::class, 'timezone'])->name('work-calendar.timezone');
            Route::post('work-calendar/rules', [\App\Http\Controllers\WorkCalendarController::class, 'rule'])->name('work-calendar.rule');
            Route::put('work-calendar/day', [\App\Http\Controllers\WorkCalendarController::class, 'day'])->name('work-calendar.day');
        });
        Route::resource('units', \App\Http\Controllers\OrganizationUnitController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware(\App\Http\Middleware\EnsureEmployeeLifecycleReady::class);
        Route::resource('users', UserController::class)->except(['show'])->middleware(\App\Http\Middleware\EnsureEmployeeLifecycleReady::class);
        Route::get('/face-enrollments', [App\Http\Controllers\FaceEnrollmentController::class, 'index'])->middleware(\App\Http\Middleware\EnsureFaceEnrollmentReady::class)->name('face-enrollments.index');
        Route::get('/face-enrollments/{enrollment}/photo', [App\Http\Controllers\FaceEnrollmentController::class, 'photo'])->middleware(\App\Http\Middleware\EnsureFaceEnrollmentReady::class)->name('face-enrollments.photo');
        Route::patch('/face-enrollments/{enrollment}', [App\Http\Controllers\FaceEnrollmentController::class, 'update'])->middleware(\App\Http\Middleware\EnsureFaceEnrollmentReady::class)->name('face-enrollments.update');
        Route::resource('companies', CompanyController::class)->only(['show', 'edit', 'update']);
        Route::get('/attendances/export-csv', [AttendanceController::class, 'exportCsv'])->name('attendances.export-csv');
        Route::resource('attendances', AttendanceController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::get('attendance-corrections', [\App\Http\Controllers\AttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
        Route::get('attendance-recap', [\App\Http\Controllers\AttendanceRecapController::class, 'index'])->name('attendance-recap.index');
        Route::get('attendance-recap/export-csv', [\App\Http\Controllers\AttendanceRecapController::class, 'exportCsv'])->name('attendance-recap.export-csv');
        Route::get('attendance-recap/{user}', [\App\Http\Controllers\AttendanceRecapController::class, 'show'])->whereNumber('user')->name('attendance-recap.show');
        Route::post('submission-decisions/{kind}/{id}', [\App\Http\Controllers\SubmissionApprovalController::class, 'decision'])->where('kind', 'permission|overtime|reimbursement')->whereNumber('id')->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class)->name('submission-approvals.decision');
        Route::resource('permissions', PermissionController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->middleware([\App\Http\Middleware\EnsureSubmissionApprovalReady::class, \App\Http\Middleware\EnsureLeavePolicyReady::class]);
        Route::resource('qr_absens', QrAbsenController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/verifikasi/export-csv', [VerifikasiPensiunController::class, 'exportCsv'])->name('verifikasi.export-csv');
        Route::resource('verifikasi', VerifikasiPensiunController::class)->only(['index']);
        Route::resource('reimbursements', App\Http\Controllers\ReimbursementController::class)->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class);
        Route::resource('shifts', App\Http\Controllers\ShiftController::class);
        Route::resource('overtimes', OvertimeController::class)->middleware(\App\Http\Middleware\EnsureSubmissionApprovalReady::class);

        Route::get('/qr-absens/{id}/download', [QrAbsenController::class, 'downloadPDF'])->name('qr_absens.download');
    });
});
