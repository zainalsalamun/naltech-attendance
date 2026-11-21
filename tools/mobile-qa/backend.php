<?php

// Keep PHP 8.4 dependency deprecations out of JSON responses in this QA server.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

// Only an explicitly prepared disposable directory can host this QA server.
$qa = getenv('NALTECH_QA_DIR');
if (! $qa || ! is_file($qa.'/fixture.json') || ! is_file($qa.'/.naltech-qa')) {
    throw new RuntimeException('Prepare an isolated QA directory first.');
}
$root = dirname(__DIR__, 2).'/apps/backend';
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('q', 32)),
    'APP_URL' => 'http://127.0.0.1:8018',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $qa.'/qa.sqlite', 'DB_URL' => '',
    'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'FILESYSTEM_DISK' => 'local',
    'APP_CONFIG_CACHE' => $qa.'/cache/config.php', 'APP_ROUTES_CACHE' => $qa.'/cache/routes.php',
    'APP_EVENTS_CACHE' => $qa.'/cache/events.php', 'APP_SERVICES_CACHE' => $qa.'/cache/services.php',
    'APP_PACKAGES_CACHE' => $qa.'/cache/packages.php',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useEnvironmentPath($qa);
$app->useStoragePath($qa.'/storage');
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
config(['app.timezone' => 'Asia/Jakarta']);
if (is_file($qa.'/.naltech-face-poc') && is_file($qa.'/face-poc.json')) {
    $faceFixture = json_decode(file_get_contents($qa.'/face-poc.json'), true);
    config(['face.preview_enabled' => true, 'face.provider' => 'self_hosted',
        'face.self_hosted_url' => 'http://127.0.0.1:8021', 'face.self_hosted_key' => $faceFixture['service_key']]);
}
date_default_timezone_set('Asia/Jakarta');
// Clock simulation is available only in the explicitly isolated QA bootstrap.
if (is_file($qa.'/.naltech-shift-qa') && is_file($qa.'/shift-clock.json')) {
    $clock=json_decode(file_get_contents($qa.'/shift-clock.json'),true);
    $instant=\Carbon\CarbonImmutable::parse($clock['local'],'Asia/Jakarta');
    \Carbon\CarbonImmutable::setTestNow($instant);
    \Carbon\Carbon::setTestNow($instant);
    \Illuminate\Support\Carbon::setTestNow($instant);
}

if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $qa.'/qa.sqlite') {
    throw new RuntimeException('QA database isolation failed.');
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'check-views') {
    Illuminate\Support\Facades\Artisan::call('view:cache');
    try {
        foreach (glob(config('view.compiled').'/*.php') as $view) {
            token_get_all(file_get_contents($view), TOKEN_PARSE);
        }
    } finally {
        Illuminate\Support\Facades\Artisan::call('view:clear');
    }
    echo "Disposable view compilation passed; cache cleared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'seed') {
    if (Illuminate\Support\Facades\Schema::hasTable('users')) {
        throw new RuntimeException('QA fixtures already exist; use a fresh directory.');
    }
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $fixture = json_decode(file_get_contents($qa.'/fixture.json'), true);
    foreach (['mobile' => 'None', 'api' => 'None', 'qr' => 'QR', 'face' => 'Face'] as $name => $method) {
        $company = App\Models\Company::create([
            'name' => 'QA Workspace '.$name, 'email' => $name.'@example.test', 'address' => 'Lokasi emulator QA',
            'latitude' => '37.4219999', 'longitude' => '-122.0840575', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => $method,
        ]);
        $user = App\Models\User::factory()->create([
            'name' => 'Penguji '.ucfirst($name), 'email' => $name.'@example.test', 'password' => $fixture['password'],
            'phone' => '08123456789', 'role' => 'staff', 'department' => 'QA', 'position' => 'Penguji',
        ]);
        $user->forceFill(['company_id' => $company->id])->save();
        if ($name === 'face') {
            $reviewer = App\Models\User::factory()->create([
                'name' => 'HR Penguji', 'email' => 'face-reviewer@example.test',
                'password' => $fixture['password'], 'role' => 'hrd',
            ]);
            $reviewer->forceFill(['company_id' => $company->id])->save();
        }
        if ($name === 'qr') {
            $qr = new App\Models\QrAbsen(['date' => now()->toDateString(), 'qr_checkin' => 'qa-checkin', 'qr_checkout' => 'qa-checkout']);
            $qr->company_id = $company->id;
            $qr->save();
        }
    }
    echo "Disposable QA database prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'workflow-fixtures') {
    $fixture = json_decode(file_get_contents($qa.'/fixture.json'), true);
    $owner = App\Models\User::withoutGlobalScopes()->where('email', 'face@example.test')->firstOrFail();
    $manager = null;
    foreach (['manager' => 'supervisor', 'backup' => 'admin'] as $name => $role) {
        $user = App\Models\User::factory()->create(['name' => 'QA '.ucfirst($name), 'email' => $name.'@example.test', 'role' => $role, 'password' => $fixture['password']]);
        $user->forceFill(['company_id' => $owner->company_id])->save();
        if ($name === 'manager') { $manager = $user; }
    }
    $owner->forceFill(['manager_id' => $manager->id])->save();
    echo "Disposable workflow reviewers prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'mobile-leave-fixtures') {
    $owner = App\Models\User::withoutGlobalScopes()->where('email', 'face@example.test')->firstOrFail();
    $hr = App\Models\User::withoutGlobalScopes()->where('email', 'face-reviewer@example.test')->firstOrFail();
    $manager = App\Models\User::withoutGlobalScopes()->where('email', 'manager@example.test')->firstOrFail();
    $backup = App\Models\User::withoutGlobalScopes()->where('email', 'backup@example.test')->firstOrFail();
    foreach (['MOBILE_PROOF' => true, 'MOBILE_OPTIONAL' => false] as $code => $required) {
        app(App\Services\LeavePolicy::class)->save($hr, ['code' => $code, 'name' => $required ? 'Cuti dengan bukti QA' : 'Cuti tanpa bukti QA', 'is_active' => true, 'deducts_balance' => true, 'annual_quota_days' => 12, 'requires_attachment' => $required, 'requirements' => 'Koordinasikan jadwal dengan atasan.', 'version' => 0, 'change_reason' => 'Fixture mobile terisolasi']);
    }
    $steps = [['label' => 'Atasan', 'type' => 'manager', 'reviewer_id' => null, 'fallback_id' => $backup->id], ['label' => 'HR', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id]];
    Illuminate\Support\Facades\DB::table('approval_workflows')->insert(['company_id' => $owner->company_id, 'kind' => 'permission', 'steps' => json_encode($steps), 'actor_id' => $hr->id, 'created_at' => now()]);
    echo "Disposable mobile leave policies prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'approval-fixtures') {
    $owner = App\Models\User::withoutGlobalScopes()->where('email', 'face@example.test')->firstOrFail();
    $reviewer = App\Models\User::withoutGlobalScopes()->where('email', 'face-reviewer@example.test')->firstOrFail();
    foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
        foreach (['approved', 'rejected'] as $status) {
            $class = App\Services\SubmissionApproval::model($kind);
            $fields = match ($kind) {
                'permission' => ['date_permission' => '2026-10-21', 'reason' => 'Keperluan keluarga QA', 'is_approved' => false],
                'overtime' => ['date' => '2026-10-21', 'duration' => 90, 'description' => 'Pekerjaan QA', 'status' => 'pending'],
                'reimbursement' => ['date' => '2026-10-21', 'amount' => '15000.25', 'description' => 'Transportasi QA', 'status' => 'pending'],
            };
            $row = $class::create(['user_id' => $owner->id] + $fields);
            app(App\Services\SubmissionApproval::class)->decide($reviewer, $kind, $row->id, $status, 0, 'Dokumen '.$status.' oleh HR QA');
        }
    }
    echo "Disposable approval fixtures prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'approval-ui-fixtures') {
    $fixture = json_decode(file_get_contents($qa.'/fixture.json'), true);
    $password = $fixture['password'];

    // 1. Setup Company A (Naltech Primary QA)
    $companyA = App\Models\Company::firstOrCreate(['email' => 'qa-primary@example.test'], [
        'name' => 'Naltech Primary QA',
        'address' => 'Jakarta Barat',
        'latitude' => '-6.200000',
        'longitude' => '106.816666',
        'radius_km' => '1.0',
        'time_in' => '08:00',
        'time_out' => '17:00',
        'attendance_type' => 'None',
        'timezone' => 'Asia/Jakarta',
    ]);
    $companyA->forceFill(['timezone' => 'Asia/Jakarta'])->save();

    // 2. Setup Company B (Foreign Company)
    $companyB = App\Models\Company::firstOrCreate(['email' => 'qa-foreign@example.test'], [
        'name' => 'Foreign Corporation QA',
        'address' => 'Surabaya',
        'latitude' => '-7.250445',
        'longitude' => '112.768845',
        'radius_km' => '1.0',
        'time_in' => '08:00',
        'time_out' => '17:00',
        'attendance_type' => 'None',
        'timezone' => 'Asia/Jakarta',
    ]);

    // Clean prior QA run data for idempotency
    $allQaCompanyIds = [$companyA->id, $companyB->id];
    $qaUserIds = App\Models\User::withoutGlobalScopes()->whereIn('company_id', $allQaCompanyIds)->pluck('id');
    Illuminate\Support\Facades\DB::table('submission_approval_events')->whereIn('actor_id', $qaUserIds)->delete();
    Illuminate\Support\Facades\DB::table('approval_workflows')->whereIn('company_id', $allQaCompanyIds)->delete();
    Illuminate\Support\Facades\DB::table('leave_balance_ledgers')->whereIn('user_id', $qaUserIds)->delete();
    Illuminate\Support\Facades\DB::table('leave_balances')->whereIn('user_id', $qaUserIds)->delete();
    App\Models\Permission::withoutGlobalScopes()->whereIn('user_id', $qaUserIds)->delete();
    App\Models\Overtime::withoutGlobalScopes()->whereIn('user_id', $qaUserIds)->delete();
    App\Models\Reimbursement::withoutGlobalScopes()->whereIn('user_id', $qaUserIds)->delete();

    // Work calendar for Company A
    Illuminate\Support\Facades\DB::table('work_calendar_rules')->updateOrInsert(
        ['company_id' => $companyA->id, 'effective_from' => '2026-01-01'],
        ['weekdays' => json_encode([1, 2, 3, 4, 5]), 'created_at' => now(), 'updated_at' => now()]
    );

    // 3. Create users in Company A
    $userA = function ($email, $name, $role, $attrs = []) use ($companyA, $password) {
        $u = App\Models\User::withoutGlobalScopes()->firstOrNew(['email' => $email]);
        $u->forceFill(array_merge([
            'name' => $name,
            'password' => $password,
            'role' => $role,
            'company_id' => $companyA->id,
            'department' => 'Engineering',
            'position' => ucfirst($role),
            'phone' => '08123456780',
        ], $attrs));
        $u->save();
        return $u;
    };

    $supervisor = $userA('supervisor@example.test', 'QA Supervisor Direct', 'supervisor');
    $hr = $userA('hr@example.test', 'QA HR Manager', 'hrd');
    $backup = $userA('backup@example.test', 'QA Admin Fallback', 'admin');
    $unrelated = $userA('unrelated@example.test', 'QA Unrelated Supervisor', 'supervisor');
    $archivedSupervisor = $userA('archived-supervisor@example.test', 'QA Archived Supervisor', 'supervisor', ['archived_at' => now()->subDay()]);
    $inactiveHr = $userA('inactive-hr@example.test', 'QA Inactive HR', 'hrd', ['employment_status' => 'inactive']);

    $staff = $userA('staff@example.test', 'QA Staff Submitter', 'staff', ['manager_id' => $supervisor->id]);

    // 4. Create users in Company B
    $foreignHr = App\Models\User::withoutGlobalScopes()->firstOrNew(['email' => 'foreign-hr@example.test']);
    $foreignHr->forceFill([
        'name' => 'Foreign HR User',
        'password' => $password,
        'role' => 'hrd',
        'company_id' => $companyB->id,
        'department' => 'Foreign Dept',
        'position' => 'HR',
        'phone' => '08123456789',
    ])->save();

    $foreignSupervisor = App\Models\User::withoutGlobalScopes()->firstOrNew(['email' => 'foreign-sup@example.test']);
    $foreignSupervisor->forceFill([
        'name' => 'Foreign Supervisor User',
        'password' => $password,
        'role' => 'supervisor',
        'company_id' => $companyB->id,
        'department' => 'Foreign Dept',
        'position' => 'Supervisor',
        'phone' => '08123456788',
    ])->save();

    // 5. Leave types in Company A
    $leaveTypeAnnual = App\Models\LeaveType::where('company_id', $companyA->id)->where('code', 'ANNUAL')->first() ?? new App\Models\LeaveType;
    $leaveTypeAnnual->forceFill([
        'company_id' => $companyA->id,
        'code' => 'ANNUAL',
        'name' => 'Cuti Tahunan QA',
        'is_active' => true,
        'deducts_balance' => true,
        'annual_quota_days' => 12,
        'requires_attachment' => false,
    ])->save();

    $leaveTypeNonBalance = App\Models\LeaveType::where('company_id', $companyA->id)->where('code', 'UNPAID')->first() ?? new App\Models\LeaveType;
    $leaveTypeNonBalance->forceFill([
        'company_id' => $companyA->id,
        'code' => 'UNPAID',
        'name' => 'Izin Khusus Tanpa Potong Saldo',
        'is_active' => true,
        'deducts_balance' => false,
        'annual_quota_days' => 0,
        'requires_attachment' => false,
    ])->save();

    // Allocate 30 days to staff so multiple reservations succeed
    $balanceAnnual = app(App\Services\LeaveBalanceService::class)->allocate($hr, $staff, $leaveTypeAnnual, 2026, 30, 'Alokasi awal cuti 2026');

    // 6. Approval Workflows for Company A
    $permissionSteps = [
        ['label' => 'Atasan Langsung', 'type' => 'manager', 'reviewer_id' => null, 'fallback_id' => $backup->id],
        ['label' => 'HR Approval', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id],
    ];
    Illuminate\Support\Facades\DB::table('approval_workflows')->insert([
        'company_id' => $companyA->id,
        'kind' => 'permission',
        'steps' => json_encode($permissionSteps),
        'actor_id' => $hr->id,
        'created_at' => now(),
    ]);

    $overtimeSteps = [
        ['label' => 'Atasan Langsung', 'type' => 'manager', 'reviewer_id' => null, 'fallback_id' => $backup->id],
    ];
    Illuminate\Support\Facades\DB::table('approval_workflows')->insert([
        'company_id' => $companyA->id,
        'kind' => 'overtime',
        'steps' => json_encode($overtimeSteps),
        'actor_id' => $hr->id,
        'created_at' => now(),
    ]);

    // Reimbursement has empty workflow []
    Illuminate\Support\Facades\DB::table('approval_workflows')->insert([
        'company_id' => $companyA->id,
        'kind' => 'reimbursement',
        'steps' => json_encode([]),
        'actor_id' => $hr->id,
        'created_at' => now(),
    ]);

    // 7. Generate Seed Submissions:
    // Create 12 pending permissions for staff to thoroughly exercise pagination (>10 items per page)
    for ($i = 1; $i <= 12; $i++) {
        $p = App\Models\Permission::create([
            'user_id' => $staff->id,
            'leave_type_id' => $leaveTypeAnnual->id,
            'date_permission' => sprintf('2026-11-%02d', $i),
            'start_date' => sprintf('2026-11-%02d', $i),
            'end_date' => sprintf('2026-11-%02d', $i),
            'duration_days' => 1,
            'reason' => 'Pengajuan cuti berkala pagination #' . $i,
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);
        // Reserve 1 day from balance
        app(App\Services\LeaveBalanceService::class)->reserve($balanceAnnual->fresh(), $p, $staff, 'Pengajuan cuti #'.$i);
    }

    // B04: An overtime submitted by supervisor
    $supOvertime = App\Models\Overtime::create([
        'user_id' => $supervisor->id,
        'date' => '2026-11-15',
        'duration' => 60,
        'description' => 'Lembur proyek supervisor (self-submission)',
        'status' => 'pending',
        'approval_version' => 0,
    ]);

    // B05: A permission where step 0 primary reviewer is archived -> fallback (backup) is actionable
    $archivedPrimaryRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'date_permission' => '2026-11-16',
        'reason' => 'Pengajuan dengan peninjau utama diarsipkan',
        'is_approved' => false,
        'status' => 'pending',
        'approval_version' => 0,
    ]);
    $archivedPrimaryRow->forceFill([
        'approval_steps' => [
            ['label' => 'Tahap 1 Archived', 'type' => 'user', 'reviewer_id' => $archivedSupervisor->id, 'fallback_id' => $backup->id],
        ],
        'approval_step' => 0,
        'approval_reviewer_ids' => [$archivedSupervisor->id, $backup->id],
    ])->save();

    // B06: A permission where reviewer_id and fallback_id are both null
    $nullReviewerRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'date_permission' => '2026-11-17',
        'reason' => 'Pengajuan peninjau kosong (tiga nilai logika null)',
        'is_approved' => false,
        'status' => 'pending',
        'approval_version' => 0,
    ]);
    $nullReviewerRow->forceFill([
        'approval_steps' => [
            ['label' => 'Tahap Peninjau Kosong', 'type' => 'user', 'reviewer_id' => null, 'fallback_id' => null],
        ],
        'approval_step' => 0,
        'approval_reviewer_ids' => [],
    ])->save();

    // Reimbursement without workflow []
    $reimbursementRow = App\Models\Reimbursement::create([
        'user_id' => $staff->id,
        'date' => '2026-11-17',
        'amount' => 175000.00,
        'description' => 'Transportasi meeting klien (tanpa workflow)',
        'status' => 'pending',
        'approval_version' => 0,
    ]);

    // B07: Legacy data needs_review
    $legacyRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'date_permission' => '2026-10-01',
        'reason' => 'Pengajuan legacy data lama',
        'is_approved' => false,
        'status' => 'needs_review',
        'approval_legacy' => true,
        'approval_version' => 0,
    ]);

    // B08-B11: Dedicated permission for decision flow test
    $flowPermission = App\Models\Permission::create([
        'user_id' => $staff->id,
        'leave_type_id' => $leaveTypeAnnual->id,
        'date_permission' => '2026-11-18',
        'start_date' => '2026-11-18',
        'end_date' => '2026-11-18',
        'duration_days' => 1,
        'reason' => 'Pengajuan untuk verifikasi tinjauan keputusan UI',
        'is_approved' => false,
        'status' => 'pending',
        'approval_version' => 0,
    ]);
    app(App\Services\LeaveBalanceService::class)->reserve($balanceAnnual->fresh(), $flowPermission, $staff, 'Pengajuan alur B08');

    // B12-B13: Approved permission requested for cancellation (will test rejection)
    $cancelRejectRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'leave_type_id' => $leaveTypeAnnual->id,
        'date_permission' => '2026-11-20',
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
        'duration_days' => 3,
        'reason' => 'Cuti liburan akhir pekan panjang (untuk uji tolak batal)',
        'is_approved' => true,
        'status' => 'approved',
        'approval_version' => 2,
    ]);
    app(App\Services\LeaveBalanceService::class)->reserve($balanceAnnual->fresh(), $cancelRejectRow, $staff, 'Cadangan cuti B12');
    app(App\Services\LeaveBalanceService::class)->useReserved($cancelRejectRow, $hr, 'Penggunaan cuti approved B12');
    $cancelRejectRow->forceFill([
        'status' => 'cancellation_requested',
        'cancellation_reason' => 'Acara keluarga dibatalkan oleh pihak penyelenggara',
        'approval_version' => 3,
    ])->save();

    // B14: Approved permission requested for cancellation (will test approval)
    $cancelApproveRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'leave_type_id' => $leaveTypeAnnual->id,
        'date_permission' => '2026-11-23',
        'start_date' => '2026-11-23',
        'end_date' => '2026-11-24',
        'duration_days' => 2,
        'reason' => 'Cuti workshop luar kota (untuk uji setujui batal)',
        'is_approved' => true,
        'status' => 'approved',
        'approval_version' => 2,
    ]);
    app(App\Services\LeaveBalanceService::class)->reserve($balanceAnnual->fresh(), $cancelApproveRow, $staff, 'Cadangan cuti B14');
    app(App\Services\LeaveBalanceService::class)->useReserved($cancelApproveRow, $hr, 'Penggunaan cuti approved B14');
    $cancelApproveRow->forceFill([
        'status' => 'cancellation_requested',
        'cancellation_reason' => 'Workshop ditunda tahun depan',
        'approval_version' => 3,
    ])->save();

    // B15: Non-balance leave type permission
    $nonBalanceRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'leave_type_id' => $leaveTypeNonBalance->id,
        'date_permission' => '2026-11-25',
        'start_date' => '2026-11-25',
        'end_date' => '2026-11-25',
        'duration_days' => 1,
        'reason' => 'Izin non-saldo keluarga',
        'is_approved' => false,
        'status' => 'pending',
        'approval_version' => 0,
    ]);

    // B18: Long text and HTML characters
    $xssRow = App\Models\Permission::create([
        'user_id' => $staff->id,
        'date_permission' => '2026-11-26',
        'reason' => '<script>alert("xss")</script> & <b>bold formatting</b> dengan penjelasan yang sangat panjang untuk menguji wrap dan layout pada tampilan desktop maupun sempit. '.str_repeat('Lorem ipsum dolor sit amet. ', 15),
        'is_approved' => false,
        'status' => 'pending',
        'approval_version' => 0,
    ]);

    // Write metadata for browser script
    file_put_contents($qa.'/approval-ui-meta.json', json_encode([
        'companyA_id' => $companyA->id,
        'companyB_id' => $companyB->id,
        'users' => [
            'staff' => ['email' => 'staff@example.test', 'id' => $staff->id],
            'supervisor' => ['email' => 'supervisor@example.test', 'id' => $supervisor->id],
            'hr' => ['email' => 'hr@example.test', 'id' => $hr->id],
            'backup' => ['email' => 'backup@example.test', 'id' => $backup->id],
            'unrelated' => ['email' => 'unrelated@example.test', 'id' => $unrelated->id],
            'archived' => ['email' => 'archived-supervisor@example.test', 'id' => $archivedSupervisor->id],
            'inactive' => ['email' => 'inactive-hr@example.test', 'id' => $inactiveHr->id],
            'foreign_hr' => ['email' => 'foreign-hr@example.test', 'id' => $foreignHr->id],
            'foreign_supervisor' => ['email' => 'foreign-sup@example.test', 'id' => $foreignSupervisor->id],
        ],
        'rows' => [
            'flow_permission_id' => $flowPermission->id,
            'cancel_reject_permission_id' => $cancelRejectRow->id,
            'cancel_approve_permission_id' => $cancelApproveRow->id,
            'legacy_permission_id' => $legacyRow->id,
            'archived_permission_id' => $archivedPrimaryRow->id,
            'null_reviewer_permission_id' => $nullReviewerRow->id,
            'non_balance_permission_id' => $nonBalanceRow->id,
            'xss_permission_id' => $xssRow->id,
            'supervisor_overtime_id' => $supOvertime->id,
            'reimbursement_id' => $reimbursementRow->id,
        ]
    ], JSON_PRETTY_PRINT));

    echo "Disposable approval UI verification fixtures prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'h5-fixtures') {
    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF');
    App\Models\Company::whereIn('email', ['h5-company-a@example.test', 'foreign-company@example.test'])->each(function ($c) {
        App\Models\AttendanceCorrectionEvent::where('company_id', $c->id)->delete();
        App\Models\Attendance::whereHas('user', fn ($q) => $q->where('company_id', $c->id))->delete();
        App\Models\Permission::whereHas('user', fn ($q) => $q->where('company_id', $c->id))->delete();
        App\Models\User::where('company_id', $c->id)->update(['manager_id' => null]);
        App\Models\User::where('company_id', $c->id)->delete();
        App\Models\OrganizationUnit::where('company_id', $c->id)->delete();
        App\Models\Shift::where('company_id', $c->id)->delete();
        Illuminate\Support\Facades\DB::table('work_calendar_rules')->where('company_id', $c->id)->delete();
        $c->delete();
    });
    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');

    $companyA = App\Models\Company::create([
        'name' => 'QA H5 Utama', 'email' => 'h5-company-a@example.test', 'address' => 'Jakarta Barat',
        'latitude' => '-6.200000', 'longitude' => '106.816666', 'radius_km' => '0.5',
        'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None',
    ]);
    $companyA->forceFill(['timezone' => 'Asia/Jakarta'])->save();

    Illuminate\Support\Facades\DB::table('work_calendar_rules')->insert([
        'company_id' => $companyA->id,
        'effective_from' => '2026-01-01',
        'weekdays' => json_encode([1, 2, 3, 4, 5]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $unitEng = App\Models\OrganizationUnit::create(['company_id' => $companyA->id, 'code' => 'ENG', 'name' => 'Engineering', 'is_active' => true]);
    $unitMkt = App\Models\OrganizationUnit::create(['company_id' => $companyA->id, 'code' => 'MKT', 'name' => 'Marketing', 'is_active' => true]);

    $shiftRegular = App\Models\Shift::create(['company_id' => $companyA->id, 'name' => 'Reguler', 'time_in' => '08:00:00', 'time_out' => '17:00:00']);
    $shiftNight = App\Models\Shift::create(['company_id' => $companyA->id, 'name' => 'Malam', 'time_in' => '22:00:00', 'time_out' => '06:00:00']);

    $fixture = json_decode(file_get_contents($qa.'/fixture.json'), true);
    $pwd = $fixture['password'];

    $hr = App\Models\User::factory()->create(['name' => 'QA HR Manager', 'email' => 'hr@example.test', 'password' => $pwd, 'role' => 'hrd']);
    $hr->forceFill(['company_id' => $companyA->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01'])->save();

    $supervisor = App\Models\User::factory()->create(['name' => 'QA Supervisor', 'email' => 'supervisor@example.test', 'password' => $pwd, 'role' => 'supervisor']);
    $supervisor->forceFill(['company_id' => $companyA->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01', 'organization_unit_id' => $unitEng->id])->save();

    $subordinate = App\Models\User::factory()->create(['name' => 'QA Subordinate Staff', 'email' => 'subordinate@example.test', 'password' => $pwd, 'role' => 'staff']);
    $subordinate->forceFill(['company_id' => $companyA->id, 'manager_id' => $supervisor->id, 'organization_unit_id' => $unitEng->id, 'shift_id' => $shiftRegular->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01'])->save();

    $otherStaff = App\Models\User::factory()->create(['name' => 'QA Other Staff', 'email' => 'other@example.test', 'password' => $pwd, 'role' => 'staff']);
    $otherStaff->forceFill(['company_id' => $companyA->id, 'organization_unit_id' => $unitMkt->id, 'shift_id' => $shiftRegular->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01'])->save();

    $nightStaff = App\Models\User::factory()->create(['name' => 'QA Night Staff', 'email' => 'night@example.test', 'password' => $pwd, 'role' => 'staff']);
    $nightStaff->forceFill(['company_id' => $companyA->id, 'manager_id' => $supervisor->id, 'organization_unit_id' => $unitEng->id, 'shift_id' => $shiftNight->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01'])->save();

    $inactiveStaff = App\Models\User::factory()->create(['name' => 'QA Inactive Staff', 'email' => 'inactive-staff@example.test', 'password' => $pwd, 'role' => 'staff']);
    $inactiveStaff->forceFill(['company_id' => $companyA->id, 'employment_status' => 'inactive', 'archived_at' => now(), 'joined_on' => '2026-01-01', 'left_on' => '2026-10-25'])->save();

    $inactiveUser = App\Models\User::factory()->create(['name' => 'QA Blocked User', 'email' => 'blocked@example.test', 'password' => $pwd, 'role' => 'hrd']);
    $inactiveUser->forceFill(['company_id' => $companyA->id, 'employment_status' => 'inactive', 'archived_at' => now(), 'joined_on' => '2026-01-01'])->save();

    $companyB = App\Models\Company::create([
        'name' => 'QA Foreign Company', 'email' => 'foreign-company@example.test', 'address' => 'Surabaya',
        'latitude' => '-7.250445', 'longitude' => '112.768845', 'radius_km' => '0.5',
        'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None',
    ]);
    $foreignUser = App\Models\User::factory()->create(['name' => 'QA Foreign HR', 'email' => 'foreign-hr@example.test', 'password' => $pwd, 'role' => 'hrd']);
    $foreignUser->forceFill(['company_id' => $companyB->id, 'employment_status' => 'active', 'joined_on' => '2026-01-01'])->save();

    // 1. Regular attendance for Subordinate (2026-10-21)
    $attRegular = App\Models\Attendance::create([
        'user_id' => $subordinate->id,
        'date' => '2026-10-21',
        'time_in' => '08:30:00',
        'time_out' => '17:00:00',
        'latlon_in' => '-6.200000,106.816666',
        'latlon_out' => '-6.200000,106.816666',
        'checked_in_at' => '2026-10-21 01:30:00',
        'checked_out_at' => '2026-10-21 10:00:00',
        'attendance_timezone' => 'Asia/Jakarta',
        'scheduled_name' => 'Reguler',
        'scheduled_start_at' => '2026-10-21 01:00:00',
        'version' => 0,
    ]);

    // 2. Overnight attendance for NightStaff (2026-10-21 into 2026-10-22)
    $attNight = App\Models\Attendance::create([
        'user_id' => $nightStaff->id,
        'date' => '2026-10-21',
        'time_in' => '22:15:00',
        'time_out' => '05:45:00',
        'latlon_in' => '-6.200000,106.816666',
        'latlon_out' => '-6.200000,106.816666',
        'checked_in_at' => '2026-10-21 15:15:00',
        'checked_out_at' => '2026-10-21 22:45:00',
        'attendance_timezone' => 'Asia/Jakarta',
        'scheduled_name' => 'Malam',
        'scheduled_start_at' => '2026-10-21 15:00:00',
        'version' => 0,
    ]);

    // 3. Attendance for OtherStaff (not managed by supervisor)
    $attOther = App\Models\Attendance::create([
        'user_id' => $otherStaff->id,
        'date' => '2026-10-21',
        'time_in' => '08:00:00',
        'time_out' => '17:00:00',
        'latlon_in' => '-6.200000,106.816666',
        'latlon_out' => '-6.200000,106.816666',
        'checked_in_at' => '2026-10-21 01:00:00',
        'checked_out_at' => '2026-10-21 10:00:00',
        'attendance_timezone' => 'Asia/Jakarta',
        'scheduled_name' => 'Reguler',
        'scheduled_start_at' => '2026-10-21 01:00:00',
        'version' => 0,
    ]);

    // Permissions: Cancellation requested cuti for Subordinate on 2026-10-23
    $permCancel = App\Models\Permission::create([
        'user_id' => $subordinate->id,
        'date_permission' => '2026-10-23',
        'start_date' => '2026-10-23',
        'end_date' => '2026-10-23',
        'duration_days' => 1,
        'reason' => 'Cuti izin keluarga (dalam permohonan pembatalan)',
        'status' => 'cancellation_requested',
        'is_approved' => true,
        'approval_step' => 1,
    ]);

    // Additional members to test pagination > 15
    for ($i = 1; $i <= 14; $i++) {
        $pUser = App\Models\User::factory()->create([
            'name' => 'QA Batch Member ' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'email' => 'h5-batch' . $i . '@example.test',
            'password' => $pwd,
            'role' => 'staff',
        ]);
        $pUser->forceFill([
            'company_id' => $companyA->id,
            'organization_unit_id' => ($i % 2 === 0 ? $unitEng->id : $unitMkt->id),
            'employment_status' => 'active',
            'joined_on' => '2026-01-01',
        ])->save();
    }

    file_put_contents($qa.'/h5-meta.json', json_encode([
        'company_id' => $companyA->id,
        'foreign_company_id' => $companyB->id,
        'users' => [
            'hr' => ['email' => 'hr@example.test', 'id' => $hr->id],
            'supervisor' => ['email' => 'supervisor@example.test', 'id' => $supervisor->id],
            'subordinate' => ['email' => 'subordinate@example.test', 'id' => $subordinate->id],
            'other_staff' => ['email' => 'other@example.test', 'id' => $otherStaff->id],
            'night_staff' => ['email' => 'night@example.test', 'id' => $nightStaff->id],
            'blocked' => ['email' => 'blocked@example.test', 'id' => $inactiveUser->id],
            'foreign_hr' => ['email' => 'foreign-hr@example.test', 'id' => $foreignUser->id],
        ],
        'units' => [
            'eng' => ['id' => $unitEng->id, 'name' => 'Engineering'],
            'mkt' => ['id' => $unitMkt->id, 'name' => 'Marketing'],
        ],
        'attendances' => [
            'regular_id' => $attRegular->id,
            'night_id' => $attNight->id,
            'other_id' => $attOther->id,
        ],
        'permissions' => [
            'cancel_id' => $permCancel->id,
        ]
    ], JSON_PRETTY_PRINT));

    echo "Disposable H5 verification fixtures prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'h1-fixtures') {
    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF');
    App\Models\Company::whereIn('email', ['h1-company@example.test'])->each(function ($c) {
        Illuminate\Support\Facades\DB::table('employee_lifecycle_events')->where('company_id', $c->id)->delete();
        App\Models\User::where('company_id', $c->id)->update(['manager_id' => null]);
        App\Models\User::where('company_id', $c->id)->delete();
        App\Models\OrganizationUnit::where('company_id', $c->id)->delete();
        $c->delete();
    });
    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');

    $company = App\Models\Company::create([
        'name' => 'QA Perusahaan H1',
        'email' => 'h1-company@example.test',
        'address' => 'Gedung QA Lt. 5, Jakarta Barat',
        'latitude' => '-6.200000',
        'longitude' => '106.816666',
        'radius_km' => '0.5',
        'time_in' => '08:00',
        'time_out' => '17:00',
        'attendance_type' => 'None',
    ]);
    $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

    $unitEng = App\Models\OrganizationUnit::create([
        'company_id' => $company->id,
        'code' => 'ENG',
        'name' => 'Divisi Rekayasa & IT',
        'is_active' => true,
    ]);
    $unitOld = App\Models\OrganizationUnit::create([
        'company_id' => $company->id,
        'code' => 'OLD',
        'name' => 'Divisi Operasional Lama',
        'is_active' => false,
    ]);

    $fixture = json_decode(file_get_contents($qa.'/fixture.json'), true);
    $pwd = $fixture['password'];

    $admin = App\Models\User::factory()->create([
        'name' => 'Admin HR H1',
        'email' => 'admin-h1@example.test',
        'password' => $pwd,
        'role' => 'hrd',
    ]);
    $admin->forceFill([
        'company_id' => $company->id,
        'employment_status' => 'active',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    $supervisor = App\Models\User::factory()->create([
        'name' => 'Budi Supervisor',
        'email' => 'supervisor-h1@example.test',
        'password' => $pwd,
        'role' => 'supervisor',
    ]);
    $supervisor->forceFill([
        'company_id' => $company->id,
        'organization_unit_id' => $unitEng->id,
        'employment_status' => 'active',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    $inactiveMgr = App\Models\User::factory()->create([
        'name' => 'Citra Ex-Manager',
        'email' => 'citra-mgr@example.test',
        'password' => $pwd,
        'role' => 'supervisor',
    ]);
    $inactiveMgr->forceFill([
        'company_id' => $company->id,
        'organization_unit_id' => $unitEng->id,
        'employment_status' => 'inactive',
        'deactivated_at' => '2026-10-20 10:00:00',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    // Staff with active manager & inactive historical unit
    $staff1 = App\Models\User::factory()->create([
        'name' => 'Deni Pratama',
        'email' => 'deni@example.test',
        'password' => $pwd,
        'role' => 'staff',
    ]);
    $staff1->forceFill([
        'company_id' => $company->id,
        'employee_number' => 'QA-001',
        'manager_id' => $supervisor->id,
        'organization_unit_id' => $unitOld->id,
        'employment_status' => 'active',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    // Archived staff with existing lifecycle event
    $archived = App\Models\User::factory()->create([
        'name' => 'Eko Purnomo',
        'email' => 'eko@example.test',
        'password' => $pwd,
        'role' => 'staff',
    ]);
    $archived->forceFill([
        'company_id' => $company->id,
        'employee_number' => 'QA-002',
        'manager_id' => $supervisor->id,
        'organization_unit_id' => $unitEng->id,
        'employment_status' => 'inactive',
        'archived_at' => '2026-10-25 10:00:00',
        'left_on' => '2026-10-25',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    Illuminate\Support\Facades\DB::table('employee_lifecycle_events')->insert([
        'company_id' => $company->id,
        'user_id' => $archived->id,
        'actor_id' => $admin->id,
        'action' => 'archived',
        'reason' => 'Kontrak kerja selesai masa berlaku',
        'before' => json_encode([
            'employee_number' => 'QA-002',
            'employment_status' => 'active',
        ]),
        'after' => json_encode([
            'employee_number' => 'QA-002',
            'employment_status' => 'inactive',
            'archived_at' => '2026-10-25 10:00:00',
            'left_on' => '2026-10-25',
        ]),
        'created_at' => '2026-10-25 10:00:00',
    ]);

    // XSS safety employee
    $xss = App\Models\User::factory()->create([
        'name' => '<script>alert("xss")</script> & <b>Karyawan Aman</b>',
        'email' => 'xss@example.test',
        'password' => $pwd,
        'role' => 'staff',
    ]);
    $xss->forceFill([
        'company_id' => $company->id,
        'employee_number' => 'QA-XSS',
        'manager_id' => $supervisor->id,
        'organization_unit_id' => $unitEng->id,
        'employment_status' => 'active',
        'joined_on' => '2026-01-01',
        'version' => 0,
    ])->save();

    // Extra staff members for pagination (>15 total)
    for ($i = 1; $i <= 14; $i++) {
        $extra = App\Models\User::factory()->create([
            'name' => 'Staf Batch ' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'email' => 'batch' . $i . '@example.test',
            'password' => $pwd,
            'role' => 'staff',
        ]);
        $extra->forceFill([
            'company_id' => $company->id,
            'employee_number' => 'QA-B' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'organization_unit_id' => $unitEng->id,
            'manager_id' => $supervisor->id,
            'employment_status' => 'active',
            'joined_on' => '2026-01-01',
            'version' => 0,
        ])->save();
    }

    file_put_contents($qa.'/h1-meta.json', json_encode([
        'company_id' => $company->id,
        'admin' => ['email' => $admin->email, 'id' => $admin->id],
        'supervisor' => ['email' => $supervisor->email, 'id' => $supervisor->id],
        'inactive_mgr' => ['email' => $inactiveMgr->email, 'id' => $inactiveMgr->id],
        'staff1' => ['email' => $staff1->email, 'id' => $staff1->id],
        'archived' => ['email' => $archived->email, 'id' => $archived->id],
        'xss' => ['email' => $xss->email, 'id' => $xss->id],
        'units' => [
            'eng' => ['id' => $unitEng->id, 'name' => $unitEng->name],
            'old' => ['id' => $unitOld->id, 'name' => $unitOld->name],
        ],
    ], JSON_PRETTY_PRINT));

    echo "Disposable H1 verification fixtures prepared.\n";
    exit;
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'shift-fixtures') {
    if (!is_file($qa.'/.naltech-shift-qa')) throw new RuntimeException('Shift QA marker required');
    $owner=App\Models\User::withoutGlobalScopes()->where('email','face@example.test')->firstOrFail();
    $hr=App\Models\User::withoutGlobalScopes()->where('email','face-reviewer@example.test')->firstOrFail();
    $company=$owner->company()->firstOrFail();
    $company->forceFill(['timezone'=>'Asia/Jakarta','attendance_type'=>'None'])->save();
    $shift=new App\Models\Shift(['name'=>'Malam emulator','time_in'=>'22:00:00','time_out'=>'06:00:00']);$shift->company_id=$company->id;$shift->save();
    app(App\Services\EffectiveShift::class)->assign($hr,['user_id'=>$owner->id,'shift_id'=>$shift->id,'effective_from'=>'2026-10-21','expected_id'=>0,'reason'=>'Fixture shift emulator']);
    foreach(['2026-10-21','2026-10-22'] as $date){$row=new App\Models\QrAbsen(['date'=>$date,'qr_checkin'=>'in-'.$date,'qr_checkout'=>'out-'.$date]);$row->company_id=$company->id;$row->save();}
    echo "Disposable shift fixtures prepared.\n";exit;
}
if (PHP_SAPI === 'cli' && in_array(($argv[1] ?? ''), ['shift-qr-mode','shift-manual-mode'],true)) {
    if (!is_file($qa.'/.naltech-shift-qa')) throw new RuntimeException('Shift QA marker required');
    App\Models\Company::where('email','face@example.test')->firstOrFail()->forceFill(['attendance_type'=>($argv[1]==='shift-qr-mode'?'QR':'None')])->save();
    echo "Disposable company uses QR.\n";exit;
}
if (PHP_SAPI === 'cli' && in_array(($argv[1] ?? ''), ['shift-offline-mode','shift-online-mode'],true)) {
    if (!is_file($qa.'/.naltech-shift-qa')) throw new RuntimeException('Shift QA marker required');
    $flag = $qa.'/.naltech-network-offline';
    if ($argv[1] === 'shift-offline-mode') {
        file_put_contents($flag, '1');
        echo "Disposable network offline enabled.\n";
    } else {
        if (is_file($flag)) @unlink($flag);
        echo "Disposable network offline disabled.\n";
    }
    exit;
}
if (PHP_SAPI !== 'cli-server') {
    throw new RuntimeException('Use the loopback QA server.');
}
$request = Illuminate\Http\Request::capture();
file_put_contents($qa.'/http-requests.log', date('H:i:s').' '.$request->method().' '.$request->path().' (offline='.(is_file($qa.'/.naltech-network-offline')?'1':'0').")\n", FILE_APPEND);
if (is_file($qa.'/.naltech-network-offline')) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Layanan sedang tidak dapat diakses (simulasi offline QA).']);
    exit;
}
if (preg_match('#^(css|js|library)/#', $request->path())) {
    $base = realpath($root.'/public');
    $asset = realpath($root.'/public/'.$request->path());
    if ($asset && str_starts_with($asset, $base.DIRECTORY_SEPARATOR) && is_file($asset)) {
        $extension = pathinfo($asset, PATHINFO_EXTENSION);
        header('Content-Type: '.(['css' => 'text/css', 'js' => 'application/javascript', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'svg' => 'image/svg+xml'][$extension] ?? mime_content_type($asset)));
        readfile($asset);
        exit;
    }
}
if (str_starts_with($request->path(), 'storage/')) {
    $base = realpath($qa.'/storage/app/public');
    $file = realpath($qa.'/storage/app/public/'.substr($request->path(), 8));
    if (! $base || ! $file || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: '.mime_content_type($file));
    readfile($file);
    exit;
}
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $http->handle($request);
$response->send();
$http->terminate($request, $response);
