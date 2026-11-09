<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\OrganizationUnit;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use App\Services\EmployeeLifecycle;
use App\Services\SubmissionApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class H1FlowRefinementTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('h', 32)));

        return $app;
    }

    private function createCompany(string $name = 'PT Naltech Prima'): Company
    {
        return Company::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'address' => 'Jl. Bisnis No. 10',
            'latitude' => '-6.200000',
            'longitude' => '106.816666',
            'radius_km' => '0.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
    }

    private function createUser(Company $company, string $role = 'staff', string $status = 'active', ?string $name = null): User
    {
        $user = User::factory()->create([
            'name' => $name ?? 'User '.rand(100, 999),
            'role' => $role,
            'employment_status' => $status,
            'password' => 'Secret123!',
        ]);
        $user->forceFill([
            'company_id' => $company->id,
            'auth_epoch' => 0,
            'version' => 0,
        ])->save();

        return $user;
    }

    /**
     * R02: Role/cakupan dan lintas tenant:
     * - Admin/HRD mengelola staf
     * - Supervisor hanya mengelola staf, dilarang menaikkan role menjadi admin/supervisor
     * - Akses lintas perusahaan ditolak 404/403
     * - Target DB tidak berubah pada saat penolakan
     */
    public function test_r02_role_scoping_and_cross_tenant_isolation(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $adminA = $this->createUser($companyA, 'admin', name: 'Admin A');
        $supervisorA = $this->createUser($companyA, 'supervisor', name: 'Supervisor A');
        $staffA = $this->createUser($companyA, 'staff', name: 'Staff A');

        $adminB = $this->createUser($companyB, 'admin', name: 'Admin B');
        $staffB = $this->createUser($companyB, 'staff', name: 'Staff B');

        // Supervisor mencoba menaikkan staf menjadi admin/supervisor via PUT
        $res = $this->actingAs($supervisorA)->putJson('/users/'.$staffA->id, [
            'name' => 'Staff A Escalated',
            'email' => $staffA->email,
            'role' => 'admin',
            'version' => 0,
        ]);
        $res->assertForbidden();
        $this->assertSame('staff', $staffA->fresh()->role);

        // Supervisor mencoba mengedit akun admin A
        $res = $this->actingAs($supervisorA)->putJson('/users/'.$adminA->id, [
            'name' => 'Admin A Renamed',
            'email' => $adminA->email,
            'version' => 0,
        ]);
        $res->assertForbidden();
        $this->assertSame('Admin A', $adminA->fresh()->name);

        // Akses lintas tenant: Admin A mencoba membaca edit staf B (harus 404)
        $this->actingAs($adminA)->getJson('/users/'.$staffB->id.'/edit')->assertNotFound();

        // Admin A mencoba mutasi PUT pada staf B lintas tenant
        $this->actingAs($adminA)->putJson('/users/'.$staffB->id, [
            'name' => 'Staff B Hijacked',
            'email' => $staffB->email,
            'version' => 0,
        ])->assertNotFound();
        $this->assertSame('Staff B', $staffB->fresh()->name);

        // Admin A mencoba DELETE (arsip) pada staf B lintas tenant
        $this->actingAs($adminA)->deleteJson('/users/'.$staffB->id, [
            'lifecycle_reason' => 'Arsip lintas tenant',
            'version' => 0,
        ])->assertNotFound();
        $this->assertNull($staffB->fresh()->archived_at);
    }

    /**
     * R03: Create/update nomor induk, tanggal, dan ID relasi invalid:
     * - Normalisasi nomor karyawan (kapital, trim)
     * - Duplikasi nomor ditolak
     * - Tanggal keluar tidak boleh sebelum bergabung
     * - Akun aktif tidak boleh memiliki tanggal keluar
     * - Relasi unit invalid atau lintas tenant ditolak
     */
    public function test_r03_employee_number_dates_and_relations_validation(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');

        // Buat akun baru dengan nomor berjarak spasi
        $res = $this->actingAs($admin)->post('/users', [
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.test',
            'password' => 'Password123!',
            'employee_number' => '  emp-099  ',
            'joined_on' => '2026-01-15',
            'role' => 'staff',
        ]);
        $res->assertRedirect('/users');
        $created = User::where('email', 'baru@example.test')->firstOrFail();
        $this->assertSame('EMP-099', $created->employee_number);

        // Nomor karyawan duplikat ditolak 422
        $this->actingAs($admin)->postJson('/users', [
            'name' => 'Karyawan Duplikat',
            'email' => 'duplikat@example.test',
            'password' => 'Password123!',
            'employee_number' => 'emp-099',
            'role' => 'staff',
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_number');

        // Tanggal keluar sebelum bergabung ditolak 422
        $this->actingAs($admin)->putJson('/users/'.$created->id, [
            'name' => $created->name,
            'email' => $created->email,
            'joined_on' => '2026-05-10',
            'left_on' => '2026-05-01',
            'employment_status' => 'inactive',
            'lifecycle_reason' => 'Keluar',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('left_on');

        // Akun aktif tidak dapat memiliki tanggal keluar
        $this->actingAs($admin)->putJson('/users/'.$created->id, [
            'name' => $created->name,
            'email' => $created->email,
            'joined_on' => '2026-01-15',
            'left_on' => '2026-05-01',
            'employment_status' => 'active',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('left_on');

        // Relasi unit dari ID yang tidak ada
        $this->actingAs($admin)->putJson('/users/'.$created->id, [
            'name' => $created->name,
            'email' => $created->email,
            'organization_unit_id' => 99999,
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_unit_id');
    }

    /**
     * R04: Preview/back/Escape/confirm:
     * - Verifikasi modal dialog preview untuk perubahan status dan arsip
     * - Alasan wajib ter-trim
     * - Submit ganda dicegah
     */
    public function test_r04_preview_modal_reason_trimming_and_archive_flow(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');
        $staff = $this->createUser($company, 'staff', name: 'Staff Preview');

        // Halaman edit memuat dialog pratinjau dan script interaksi
        $viewRes = $this->actingAs($admin)->get('/users/'.$staff->id.'/edit');
        $viewRes->assertOk();
        $viewRes->assertSee('lifecycle-preview-dialog');
        $viewRes->assertSee('Pratinjau Perubahan Status');
        $viewRes->assertSee('Dampak Akses');
        $viewRes->assertSee('Pelestarian Riwayat');

        // Halaman index memuat modal arsip terstruktur
        $indexRes = $this->actingAs($admin)->get('/users');
        $indexRes->assertOk();
        $indexRes->assertSee('archive-user-'.$staff->id);
        $indexRes->assertSee('Alasan arsip (wajib)');

        // Alasan arsip kosong hanya berisi spasi ditolak
        $this->actingAs($admin)->deleteJson('/users/'.$staff->id, [
            'lifecycle_reason' => '     ',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('lifecycle_reason');
        $this->assertNull($staff->fresh()->archived_at);

        // Arsip sah berhasil dan mencatat event tepat 1 kali
        $this->actingAs($admin)->delete('/users/'.$staff->id, [
            'lifecycle_reason' => 'Pengakhiran kontrak kerja sesuai kesepakatan',
            'version' => 0,
        ])->assertRedirect('/users');

        $staff->refresh();
        $this->assertNotNull($staff->archived_at);
        $this->assertSame('inactive', $staff->employment_status);

        $events = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->get();
        $this->assertCount(1, $events);
        $this->assertSame('archived', $events->first()->action);
        $this->assertSame('Pengakhiran kontrak kerja sesuai kesepakatan', $events->first()->reason);
    }

    /**
     * R05: Nonaktif/arsip/reaktivasi:
     * - Akses sesi/token lama ditolak setelah nonaktif/arsip
     * - Reaktivasi mencatat transisi eksplisit
     * - Sesi/token lama tetap ditolak setelah reaktivasi (wajib login ulang)
     * - Riwayat absensi/pengajuan tetap utuh
     */
    public function test_r05_deactivation_archived_reactivation_and_credential_invalidation(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');
        $staff = $this->createUser($company, 'staff', name: 'Staff Lifecycle');

        // Tambah data historis kehadiran dan pengajuan
        Attendance::create(['user_id' => $staff->id, 'date' => '2026-10-15', 'time_in' => '08:00', 'latlon_in' => '-6.2,106.8']);
        Permission::create(['user_id' => $staff->id, 'date_permission' => '2026-10-16', 'reason' => 'Sakit', 'status' => 'approved']);
        Overtime::create(['user_id' => $staff->id, 'date' => '2026-10-17', 'duration' => 2, 'status' => 'approved']);
        Reimbursement::create(['user_id' => $staff->id, 'date' => '2026-10-18', 'description' => 'Bensin', 'amount' => 50000, 'status' => 'approved']);

        $token = $staff->createToken('legacy-token')->plainTextToken;

        // Arsipkan karyawan
        $this->actingAs($admin)->delete('/users/'.$staff->id, [
            'lifecycle_reason' => 'Pensiun dini',
            'version' => 0,
        ])->assertRedirect();

        // Token lama ditolak 401
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();

        // Verifikasi seluruh data historis tetap tersimpan
        $this->assertDatabaseHas('attendances', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('permissions', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('overtimes', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('reimbursements', ['user_id' => $staff->id]);

        // Reaktivasi karyawan dari status arsip
        $reactivateRes = $this->actingAs($admin)->put('/users/'.$staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
            'employment_status' => 'active',
            'lifecycle_reason' => 'Dipekerjakan kembali sebagai konsultan paruh waktu',
            'version' => $staff->fresh()->version,
        ]);
        $reactivateRes->assertRedirect('/users');

        $staff->refresh();
        $this->assertSame('active', $staff->employment_status);
        $this->assertNull($staff->archived_at);
        $this->assertNull($staff->deactivated_at);

        // Verifikasi event reaktivasi tercatat
        $latestEvent = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->latest('id')->first();
        $this->assertSame('reactivated', $latestEvent->action);

        // Token lama tetap ditolak setelah reaktivasi (auth_epoch bumped)
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();

        // Karyawan harus login baru untuk memperoleh sesi/token baru
        $loginRes = $this->postJson('/api/login', [
            'email' => $staff->email,
            'password' => 'Secret123!',
        ]);
        $loginRes->assertOk()->assertJsonStructure(['token']);
    }

    /**
     * R06: Self-deactivation dan proteksi admin terakhir:
     * - Admin tidak dapat menonaktifkan atau mengarsipkan akun sendiri
     * - Admin aktif terakhir tidak dapat didemosi, dinonaktifkan, atau diarsipkan
     */
    public function test_r06_self_deactivation_and_last_admin_protection(): void
    {
        $company = $this->createCompany();
        $soloAdmin = $this->createUser($company, 'admin', name: 'Solo Admin');

        // Self-archive via delete ditolak
        $this->actingAs($soloAdmin)->deleteJson('/users/'.$soloAdmin->id, [
            'lifecycle_reason' => 'Arsip diri sendiri',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('employment_status');

        // Self-deactivation via PUT ditolak
        $this->actingAs($soloAdmin)->putJson('/users/'.$soloAdmin->id, [
            'name' => $soloAdmin->name,
            'email' => $soloAdmin->email,
            'employment_status' => 'inactive',
            'lifecycle_reason' => 'Nonaktifkan diri sendiri',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('employment_status');

        // Demosi admin terakhir menjadi staff ditolak
        $this->actingAs($soloAdmin)->putJson('/users/'.$soloAdmin->id, [
            'name' => $soloAdmin->name,
            'email' => $soloAdmin->email,
            'role' => 'staff',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        // Tambahkan admin kedua
        $adminTwo = $this->createUser($company, 'admin', name: 'Admin Two');

        // Admin 1 sekarang dapat mendemosi admin 2 menjadi staff
        $this->actingAs($soloAdmin)->put('/users/'.$adminTwo->id, [
            'name' => $adminTwo->name,
            'email' => $adminTwo->email,
            'role' => 'staff',
            'version' => $adminTwo->version,
        ])->assertRedirect('/users');
        $this->assertSame('staff', $adminTwo->fresh()->role);

        // Sekarang soloAdmin kembali menjadi admin tunggal, tidak dapat didemosi
        $this->actingAs($soloAdmin)->putJson('/users/'.$soloAdmin->id, [
            'name' => $soloAdmin->name,
            'email' => $soloAdmin->email,
            'role' => 'staff',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    /**
     * R07: Atasan self, siklus, nonaktif, lintas tenant, dan unit nonaktif:
     * - Atasan diri sendiri ditolak
     * - Siklus langsung (A -> B -> A) dan tidak langsung (A -> B -> C -> A) ditolak
     * - Hubungan atasan/unit nonaktif yang ada dipertahankan sebagai riwayat
     * - Penempatan baru wajib unit dan atasan aktif
     */
    public function test_r07_manager_cycles_cross_tenant_and_inactive_retention(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');

        $userA = $this->createUser($company, 'staff', name: 'User A');
        $userB = $this->createUser($company, 'staff', name: 'User B');
        $userC = $this->createUser($company, 'staff', name: 'User C');

        // Atasan diri sendiri ditolak
        $this->actingAs($admin)->putJson('/users/'.$userA->id, [
            'name' => $userA->name,
            'email' => $userA->email,
            'manager_id' => $userA->id,
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('manager_id');

        // Tetapkan A -> B dan B -> C
        $this->actingAs($admin)->put('/users/'.$userA->id, [
            'name' => $userA->name,
            'email' => $userA->email,
            'manager_id' => $userB->id,
            'version' => $userA->fresh()->version,
        ]);
        $this->actingAs($admin)->put('/users/'.$userB->id, [
            'name' => $userB->name,
            'email' => $userB->email,
            'manager_id' => $userC->id,
            'version' => $userB->fresh()->version,
        ]);

        // C mencoba menetapkan atasan A (siklus A -> B -> C -> A) ditolak
        $this->actingAs($admin)->putJson('/users/'.$userC->id, [
            'name' => $userC->name,
            'email' => $userC->email,
            'manager_id' => $userA->id,
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('manager_id');

        // Buat unit aktif dan nonaktifkan
        $unit = OrganizationUnit::create([
            'company_id' => $company->id,
            'code' => 'TECH',
            'name' => 'Technology',
            'is_active' => true,
        ]);
        $this->actingAs($admin)->put('/users/'.$userA->id, [
            'name' => $userA->name,
            'email' => $userA->email,
            'organization_unit_id' => $unit->id,
            'version' => $userA->fresh()->version,
        ]);
        $unit->update(['is_active' => false]);

        // User A tetap mempertahankan unit nonaktif historis saat update profil lain
        $this->actingAs($admin)->put('/users/'.$userA->id, [
            'name' => 'User A Renamed',
            'email' => $userA->email,
            'organization_unit_id' => $unit->id,
            'version' => $userA->fresh()->version,
        ])->assertRedirect('/users');
        $this->assertSame($unit->id, (int) $userA->fresh()->organization_unit_id);

        // Tetapi user B (yang belum memiliki unit ini) dilarang menunjuk unit nonaktif
        $this->actingAs($admin)->putJson('/users/'.$userB->id, [
            'name' => $userB->name,
            'email' => $userB->email,
            'organization_unit_id' => $unit->id,
            'version' => $userB->fresh()->version,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_unit_id');
    }

    /**
     * R08: Perubahan atasan/reviewer:
     * - Pengajuan existing mempertahankan snapshot langkah & reviewer
     * - Pengajuan baru menggunakan konfigurasi atasan saat dibuat
     * - Reviewer nonaktif dialihkan ke fallback aktif
     * - Larangan self-approval tetap ditegakkan
     */
    public function test_r08_manager_reviewer_change_snapshot_fallback_and_self_approval(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin', name: 'Admin Utama');
        $oldSupervisor = $this->createUser($company, 'supervisor', name: 'Old Supervisor');
        $newSupervisor = $this->createUser($company, 'supervisor', name: 'New Supervisor');
        $fallbackAdmin = $this->createUser($company, 'admin', name: 'Fallback Admin');

        $staff = $this->createUser($company, 'staff', name: 'Staff Pemohon');
        $staff->update(['manager_id' => $oldSupervisor->id]);

        // Konfigurasi workflow approval cuti
        DB::table('approval_workflows')->insert([
            'company_id' => $company->id,
            'kind' => 'permission',
            'steps' => json_encode([
                [
                    'label' => 'Review Atasan',
                    'type' => 'manager',
                    'reviewer_id' => null,
                    'fallback_id' => $fallbackAdmin->id,
                ],
            ]),
            'actor_id' => $admin->id,
            'created_at' => now(),
        ]);

        // Buat pengajuan cuti saat atasan masih Old Supervisor
        $perm1 = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-01',
            'reason' => 'Cuti Tahunan',
            'status' => 'pending',
        ]);
        app(ApprovalWorkflow::class)->attach($perm1);
        $perm1->save();

        // Verifikasi snapshot perm1 merekam Old Supervisor
        $steps = $perm1->fresh()->approval_steps;
        $this->assertSame($oldSupervisor->id, (int) $steps[0]['reviewer_id']);
        $this->assertSame($fallbackAdmin->id, (int) $steps[0]['fallback_id']);

        // Ganti atasan staff ke New Supervisor
        $staff->update(['manager_id' => $newSupervisor->id]);

        // Pengajuan lama (perm1) TETAP memiliki snapshot Old Supervisor
        $this->assertSame($oldSupervisor->id, (int) $perm1->fresh()->approval_steps[0]['reviewer_id']);

        // Pengajuan baru (perm2) MEREKAM New Supervisor
        $perm2 = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-02',
            'reason' => 'Cuti Kedua',
            'status' => 'pending',
        ]);
        app(ApprovalWorkflow::class)->attach($perm2);
        $perm2->save();
        $this->assertSame($newSupervisor->id, (int) $perm2->fresh()->approval_steps[0]['reviewer_id']);

        // Nonaktifkan Old Supervisor -> Reviewer perm1 otomatis fallback ke Fallback Admin
        $oldSupervisor->update(['employment_status' => 'inactive']);
        $resolvedReviewer = app(ApprovalWorkflow::class)->reviewer($perm1->fresh());
        $this->assertNotNull($resolvedReviewer);
        $this->assertSame($fallbackAdmin->id, (int) $resolvedReviewer->id);

        // Larangan self-approval: Fallback Admin tidak boleh memutuskan pengajuan miliknya sendiri
        $permOwn = Permission::create([
            'user_id' => $fallbackAdmin->id,
            'date_permission' => '2026-11-05',
            'reason' => 'Cuti Admin Sendiri',
            'status' => 'pending',
        ]);
        $this->assertFalse(app(SubmissionApproval::class)->canDecide($fallbackAdmin, $permOwn));
    }

    /**
     * R09: Audit no-op, rollback, stale form:
     * - Stale version form ditolak 409 Conflict
     * - Update no-op tidak menambah event audit baru
     * - Rollback transaksi pada error database tidak meninggalkan event parsial
     */
    public function test_r09_audit_noop_concurrency_conflict_and_rollback(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');
        $staff = $this->createUser($company, 'staff', name: 'Staff Concurrency');

        $this->assertSame(0, (int) $staff->version);

        // Update pertama berhasil menaikkan versi menjadi 1
        $res = $this->actingAs($admin)->put('/users/'.$staff->id, [
            'name' => 'Staff Concurrency V1',
            'email' => $staff->email,
            'phone' => '081234567890',
            'version' => 0,
        ]);
        $res->assertRedirect('/users');
        $staff->refresh();
        $this->assertSame(1, (int) $staff->version);
        $this->assertSame('Staff Concurrency V1', $staff->name);

        // Pengguna kedua submit dengan versi usang 0 (stale form) -> Ditolak 409 Conflict untuk JSON
        $conflictRes = $this->actingAs($admin)->putJson('/users/'.$staff->id, [
            'name' => 'Staff Stale Overwrite',
            'email' => $staff->email,
            'phone' => '089999999999',
            'version' => 0,
        ]);
        $conflictRes->assertStatus(409);
        $this->assertSame('Staff Concurrency V1', $staff->fresh()->name);
        $this->assertSame(1, (int) $staff->fresh()->version);

        // Web request: submit form dengan versi usang 0 -> Redirect back dengan mempertahankan input dan flash conflict
        $webConflictRes = $this->actingAs($admin)
            ->from('/users/'.$staff->id.'/edit')
            ->put('/users/'.$staff->id, [
                'name' => 'Staff Web Stale Input',
                'email' => $staff->email,
                'phone' => '087777777777',
                'version' => 0,
            ]);
        $webConflictRes->assertRedirect('/users/'.$staff->id.'/edit');
        $webConflictRes->assertSessionHas('conflict');
        $webConflictRes->assertSessionHasInput('name', 'Staff Web Stale Input');
        $webConflictRes->assertSessionHasInput('phone', '087777777777');

        // Web archive request: submit arsip dengan versi usang 0 -> Redirect back dengan mempertahankan input dan flash conflict
        $webArchiveConflict = $this->actingAs($admin)
            ->from('/users')
            ->delete('/users/'.$staff->id, [
                'lifecycle_reason' => 'Arsip karyawan keluar',
                'archive_user_id' => $staff->id,
                'version' => 0,
            ]);
        $webArchiveConflict->assertRedirect('/users');
        $webArchiveConflict->assertSessionHas('conflict');
        $webArchiveConflict->assertSessionHasInput('lifecycle_reason', 'Arsip karyawan keluar');
        $webArchiveConflict->assertSessionHasInput('archive_user_id', $staff->id);

        // Halaman tujuan memulihkan isi textarea alasan arsip dan menampilkan notice
        $webUsersFollow = $this->actingAs($admin)->get('/users');
        $webUsersFollow->assertOk();
        $webUsersFollow->assertSee('Arsip karyawan keluar');

        // No-op update (data sama persis) tidak menambah audit event palsu dan menampilkan notice info
        $initialEventsCount = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count();
        $noopRes = $this->actingAs($admin)->put('/users/'.$staff->id, [
            'name' => 'Staff Concurrency V1',
            'email' => $staff->email,
            'phone' => '081234567890',
            'version' => 1,
        ]);
        $noopRes->assertRedirect('/users');
        $noopRes->assertSessionHas('info', 'Tidak ada perubahan pada data karyawan.');
        $this->assertSame($initialEventsCount, DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count());
        $this->assertSame(1, (int) $staff->fresh()->version);

        // Celah tertutup: Request update langsung tanpa version ditolak 422 Unprocessable Entity
        $missingVersionUpdate = $this->actingAs($admin)->putJson('/users/'.$staff->id, [
            'name' => 'Bypass Without Version',
            'email' => $staff->email,
        ]);
        $missingVersionUpdate->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame('Staff Concurrency V1', $staff->fresh()->name);
        $this->assertSame(1, (int) $staff->fresh()->version);
        $this->assertSame($initialEventsCount, DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count());

        // Celah tertutup: Request arsip langsung tanpa version ditolak 422 Unprocessable Entity
        $missingVersionArchive = $this->actingAs($admin)->deleteJson('/users/'.$staff->id, [
            'lifecycle_reason' => 'Arsip tanpa versi',
        ]);
        $missingVersionArchive->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertNull($staff->fresh()->archived_at);
        $this->assertSame('active', $staff->fresh()->employment_status);
        $this->assertSame($initialEventsCount, DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count());

        // Request arsip dengan versi usang ditolak 409 Conflict
        $staleVersionArchive = $this->actingAs($admin)->deleteJson('/users/'.$staff->id, [
            'lifecycle_reason' => 'Arsip dengan versi usang',
            'version' => 0,
        ]);
        $staleVersionArchive->assertStatus(409);
        $this->assertNull($staff->fresh()->archived_at);
        $this->assertSame('active', $staff->fresh()->employment_status);
        $this->assertSame($initialEventsCount, DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count());

        // Pemanggilan langsung EmployeeLifecycle::save tanpa version melempar ValidationException
        $caughtException = false;
        try {
            app(EmployeeLifecycle::class)->save($admin, $staff, ['name' => 'Direct Save Without Version'], 'Direct Call');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caughtException = true;
            $this->assertArrayHasKey('version', $e->errors());
        }
        $this->assertTrue($caughtException, 'EmployeeLifecycle::save must throw ValidationException when version is missing');
        $this->assertSame('Staff Concurrency V1', $staff->fresh()->name);
        $this->assertSame($initialEventsCount, DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count());
    }

    /**
     * R10: Rekap historis dan daftar arsip:
     * - Karyawan nonaktif dan arsip dapat difilter secara eksplisit
     * - Pembacaan rekap dan riwayat tidak memutasi ledger saldo
     */
    public function test_r10_historical_listing_and_read_only_integrity(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');

        $active = $this->createUser($company, 'staff', 'active', 'Karyawan Aktif');
        $inactive = $this->createUser($company, 'staff', 'inactive', 'Karyawan Nonaktif');
        $archived = $this->createUser($company, 'staff', 'inactive', 'Karyawan Arsip');
        $archived->forceFill(['archived_at' => now()])->save();

        // Filter active
        $this->actingAs($admin)->get('/users?status=active')
            ->assertOk()
            ->assertSee('Karyawan Aktif')
            ->assertDontSee('Karyawan Nonaktif')
            ->assertDontSee('Karyawan Arsip');

        // Filter inactive
        $this->actingAs($admin)->get('/users?status=inactive')
            ->assertOk()
            ->assertDontSee('Karyawan Aktif')
            ->assertSee('Karyawan Nonaktif')
            ->assertDontSee('Karyawan Arsip');

        // Filter archived
        $this->actingAs($admin)->get('/users?status=archived')
            ->assertOk()
            ->assertDontSee('Karyawan Aktif')
            ->assertDontSee('Karyawan Nonaktif')
            ->assertSee('Karyawan Arsip');

        // Filter all
        $this->actingAs($admin)->get('/users?status=all')
            ->assertOk()
            ->assertSee('Karyawan Aktif')
            ->assertSee('Karyawan Nonaktif')
            ->assertSee('Karyawan Arsip');

        // Membaca detail arsip tidak memutasi ledger
        $ledgerCountBefore = DB::table('leave_balance_ledgers')->count();
        $this->actingAs($admin)->get('/users/'.$archived->id.'/edit')->assertOk();
        $this->assertSame($ledgerCountBefore, DB::table('leave_balance_ledgers')->count());
    }

    /**
     * R11: Keamanan XSS, teks panjang, dan tampilan respon di H1
     */
    public function test_r11_xss_safety_and_long_text_limits(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, 'admin');

        $xssName = 'Budi <script>alert("xss")</script>';
        $res = $this->actingAs($admin)->post('/users', [
            'name' => $xssName,
            'email' => 'xss@example.test',
            'password' => 'Password123!',
            'employee_number' => 'EMP-XSS',
            'role' => 'staff',
        ]);
        $res->assertRedirect('/users');

        $user = User::where('email', 'xss@example.test')->firstOrFail();

        // Di halaman list, karakter script ter-escape sebagai entitas HTML
        $indexHtml = $this->actingAs($admin)->get('/users')->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $indexHtml);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $indexHtml);

        // Teks alasan melebihi 500 karakter ditolak 422
        $longReason = str_repeat('A', 501);
        $this->actingAs($admin)->deleteJson('/users/'.$user->id, [
            'lifecycle_reason' => $longReason,
        ])->assertUnprocessable()->assertJsonValidationErrors('lifecycle_reason');
    }
}
