<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load('shift');
})->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//login
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

//logout
Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');

//company
Route::get('/submission-attachments/{kind}/{id}', [App\Http\Controllers\SubmissionAttachmentController::class, 'show'])
    ->where('kind', 'permission|reimbursement')->whereNumber('id')
    ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

Route::get('/company', [App\Http\Controllers\Api\CompanyController::class, 'show'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//checkin
Route::post('/checkin', [App\Http\Controllers\Api\AttendanceController::class, 'checkin'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//checkout
Route::post('/checkout', [App\Http\Controllers\Api\AttendanceController::class, 'checkout'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//is checkin
Route::get('/is-checkin', [App\Http\Controllers\Api\AttendanceController::class, 'isCheckedin'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//update profile
Route::post('/update-profile', [App\Http\Controllers\Api\AuthController::class, 'updateProfile'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//create permission
Route::apiResource('/api-permissions', App\Http\Controllers\Api\PermissionController::class)->only(['index', 'show', 'store'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureSubmissionApprovalReady::class]);

//notes
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureFaceEnrollmentReady::class])->group(function () {
    Route::get('/face-enrollments', [App\Http\Controllers\Api\FaceEnrollmentController::class, 'index']);
    Route::post('/face-matching-preview', [App\Http\Controllers\Api\FaceEnrollmentController::class, 'preview'])->middleware('throttle:5,1');
    Route::post('/face-enrollments', [App\Http\Controllers\Api\FaceEnrollmentController::class, 'store'])->middleware('throttle:5,1');
    Route::delete('/face-enrollments/{enrollment}', [App\Http\Controllers\Api\FaceEnrollmentController::class, 'destroy'])->middleware('throttle:10,1');
});

Route::apiResource('/api-notes', App\Http\Controllers\Api\NoteController::class)->only(['index', 'store'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//update fcm token
Route::post('/update-fcm-token', [App\Http\Controllers\Api\AuthController::class, 'updateFcmToken'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//get attendance
Route::get('/api-attendances', [App\Http\Controllers\Api\AttendanceController::class, 'index'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

Route::get('/api-user/{id}', [App\Http\Controllers\Api\UserController::class, 'getUserId'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//update user
Route::post('/api-user/edit', [App\Http\Controllers\Api\UserController::class, 'updateProfile'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

Route::post('/check-qr', [App\Http\Controllers\Api\QrAbsenController::class, 'checkQR'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//reimbursements
Route::apiResource('/api-reimbursements', App\Http\Controllers\Api\ReimbursementController::class)->only(['index', 'show', 'store'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureSubmissionApprovalReady::class]);

//shifts
Route::apiResource('/api-shifts', App\Http\Controllers\Api\ShiftController::class)->only(['index', 'show', 'store', 'update'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);

//overtimes
Route::apiResource('/api-overtimes', App\Http\Controllers\Api\OvertimeController::class)->only(['index', 'show', 'store'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureSubmissionApprovalReady::class]);

Route::post('/submission-cancellations/{kind}/{id}', [App\Http\Controllers\SubmissionApprovalController::class, 'cancel'])->where('kind', 'permission|overtime|reimbursement')->whereNumber('id')->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureSubmissionApprovalReady::class]);

Route::get('/leave-types', [App\Http\Controllers\Api\LeaveTypeController::class, 'index'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class, \App\Http\Middleware\EnsureLeavePolicyReady::class]);
Route::get('/leave-balances', [App\Http\Controllers\Api\LeaveBalanceController::class, 'index'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);
Route::post('/leave-calculate', [App\Http\Controllers\Api\LeaveBalanceController::class, 'calculate'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);
Route::get('/leave-ledgers', [App\Http\Controllers\Api\LeaveBalanceController::class, 'ledger'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompany::class]);
