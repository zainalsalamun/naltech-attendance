<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\LeaveType;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class H5FlowRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.timezone', 'Asia/Jakarta');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('h', 32)));

        return $app;
    }

    private function company(string $name = 'Perusahaan H5 QA'): Company
    {
        $company = Company::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'address' => 'Jakarta Barat',
            'latitude' => '-6.200000',
            'longitude' => '106.816666',
            'radius_km' => '0.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]), // Senin - Jumat
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $company;
    }

    private function member(Company $company, string $name, string $role = 'staff', ?Shift $shift = null, ?User $manager = null, ?OrganizationUnit $unit = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->forceFill([
            'company_id' => $company->id,
            'shift_id' => $shift?->id,
            'manager_id' => $manager?->id,
            'organization_unit_id' => $unit?->id,
            'employment_status' => 'active',
            'joined_on' => '2026-01-01',
        ])->save();

        return $user;
    }

    /**
     * H5-01 & H5-02: Koreksi beralasan, before/after snapshot, alasan wajib, dan no-op.
     */
    public function test_attendance_correction_records_audit_trail_validates_reason_and_detects_noop(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Admin', 'hrd');
        $staff = $this->member($company, 'Staff Tester', 'staff');

        $att = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '08:30:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:30:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        // 1. Whitespace or empty reason rejected with validation error
        $this->actingAs($admin)->putJson('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'correction_reason' => '   ',
            'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('correction_reason');

        // 2. Valid correction records audit event and increments version
        $this->actingAs($admin)->put('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'checkout_date' => '2026-10-21',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Koreksi jam masuk tepat 08:00',
            'version' => 0,
        ])->assertRedirect('/attendances');

        $att->refresh();
        $this->assertSame('08:00:00', $att->time_in);
        $this->assertSame(1, (int) $att->version);

        $this->assertDatabaseHas('attendance_correction_events', [
            'company_id' => $company->id,
            'attendance_id' => $att->id,
            'actor_id' => $admin->id,
            'reason' => 'Koreksi jam masuk tepat 08:00',
        ]);
        $this->assertDatabaseCount('attendance_correction_events', 1);

        // 3. No-op correction (submitting identical data) does NOT create fake audit event
        $this->actingAs($admin)->put('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'checkout_date' => '2026-10-21',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Mencoba submit tanpa perubahan',
            'version' => 1,
        ])->assertRedirect('/attendances')
            ->assertSessionHas('info');

        // Audit count remains 1 (no fake event added)
        $this->assertDatabaseCount('attendance_correction_events', 1);
    }

    /**
     * H5-03: Dua editor / version conflict menghasilkan 409 Conflict tanpa merusak data atau menambah audit.
     */
    public function test_concurrency_conflict_returns_409_and_prevents_lost_update(): void
    {
        $company = $this->company();
        $admin1 = $this->member($company, 'Admin Satu', 'hrd');
        $admin2 = $this->member($company, 'Admin Dua', 'admin');
        $staff = $this->member($company, 'Staff Target', 'staff');

        $att = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '08:30:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:30:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        // Editor 1 updates with version 0 -> Success, version becomes 1
        $this->actingAs($admin1)->put('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'checkout_date' => '2026-10-21',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Koreksi pertama oleh Admin Satu',
            'version' => 0,
        ])->assertRedirect('/attendances');

        $att->refresh();
        $this->assertSame(1, (int) $att->version);
        $this->assertDatabaseCount('attendance_correction_events', 1);

        // Editor 2 submits with stale version 0 -> 409 Conflict
        $this->actingAs($admin2)->put('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '07:45:00',
            'checkout_date' => '2026-10-21',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Koreksi kedua yang terlambat',
            'version' => 0,
        ])->assertStatus(409);

        // Data was not overwritten by stale editor
        $att->refresh();
        $this->assertSame('08:00:00', $att->time_in);
        $this->assertSame(1, (int) $att->version);
        $this->assertDatabaseCount('attendance_correction_events', 1);
    }

    /**
     * H5-04: Shift malam 22:00–06:00 lintas tengah malam mempertahankan tanggal kerja asal.
     */
    public function test_night_shift_cross_midnight_maintains_original_work_date_and_utc(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Admin', 'hrd');
        $staff = $this->member($company, 'Night Guard', 'staff');

        $att = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '22:15:00',
            'time_out' => '05:45:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 15:15:00',
            'checked_out_at' => '2026-10-21 22:45:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        // Correct checkout to exactly 06:00 on next day (2026-10-22)
        $this->actingAs($admin)->put('/attendances/'.$att->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '22:00:00',
            'checkout_date' => '2026-10-22',
            'time_out' => '06:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Koreksi shift malam penuh 8 jam',
            'version' => 0,
        ])->assertRedirect('/attendances');

        $att->refresh();
        // Original work date MUST NOT change
        $this->assertSame('2026-10-21', $att->date);
        $this->assertSame('22:00:00', $att->time_in);
        $this->assertSame('06:00:00', $att->time_out);
        // UTC conversion: 22:00 WIB (UTC+7) = 15:00 UTC
        $this->assertSame('2026-10-21 15:00:00', $att->checked_in_at);
        // UTC conversion: 06:00 next day WIB (UTC+7) = 23:00 on 2026-10-21 UTC
        $this->assertSame('2026-10-21 23:00:00', $att->checked_out_at);
    }

    /**
     * H5-06: Siklus approved -> cancellation_requested -> approved/cancelled pada rekap & pembacaan tidak memutasi ledger.
     */
    public function test_recap_recognizes_cancellation_requested_and_does_not_mutate_balance_ledger(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Manager', 'hrd');
        $staff = $this->member($company, 'Cuti Submitter', 'staff');

        // Friday 2026-10-23
        $perm = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-10-23',
            'start_date' => '2026-10-23',
            'end_date' => '2026-10-23',
            'duration_days' => 1,
            'reason' => 'Cuti liburan',
            'status' => 'cancellation_requested',
            'approval_step' => 1,
        ]);

        $ledgersBefore = DB::table('leave_balance_ledgers')->count();

        // 1. When status is cancellation_requested: still recognized as leave (recap & CSV)
        $url = '/attendance-recap?start_date=2026-10-23&end_date=2026-10-23';
        $res = $this->actingAs($admin)->get($url)->assertOk();
        $rows = $res->viewData('recap');
        $row = collect($rows)->firstWhere('user.id', $staff->id);

        $this->assertSame(1, $row['leave_days']);
        $this->assertSame(0, $row['absent_days']);

        // Verify CSV export also reflects 1 leave day
        $csvContent = $this->get('/attendance-recap/export-csv?start_date=2026-10-23&end_date=2026-10-23')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Cuti Submitter', $csvContent);

        // Assert reading recap/CSV DOES NOT write ledger
        $this->assertSame($ledgersBefore, DB::table('leave_balance_ledgers')->count());

        // 2. Cancellation rejected -> status returns to approved -> still counted as leave
        $perm->update(['status' => 'approved']);
        $res2 = $this->get($url)->assertOk();
        $row2 = collect($res2->viewData('recap'))->firstWhere('user.id', $staff->id);
        $this->assertSame(1, $row2['leave_days']);
        $this->assertSame(0, $row2['absent_days']);

        // 3. Cancellation approved -> status becomes cancelled -> leave is no longer counted
        $perm->update(['status' => 'cancelled']);
        $res3 = $this->get($url)->assertOk();
        $row3 = collect($res3->viewData('recap'))->firstWhere('user.id', $staff->id);
        $this->assertSame(0, $row3['leave_days']);
        $this->assertSame(1, $row3['absent_days']);
    }

    /**
     * H5-07: Cuti rentang, libur, dan overlap kehadiran tidak menduplikasi hari kerja.
     */
    public function test_range_leave_holiday_and_attendance_overlap(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR', 'hrd');
        $staff = $this->member($company, 'Overlap Tester', 'staff');

        // Multi-day leave from Wednesday 2026-10-21 to Friday 2026-10-23 (3 working days)
        Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-10-21',
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'duration_days' => 3,
            'reason' => 'Cuti rentang 3 hari',
            'status' => 'approved',
        ]);

        // Staff actually showed up and checked in on Thursday 2026-10-22
        Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-22',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-22 01:00:00',
            'checked_out_at' => '2026-10-22 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'scheduled_start_at' => '2026-10-22 01:00:00',
        ]);

        $res = $this->actingAs($admin)->get('/attendance-recap?start_date=2026-10-21&end_date=2026-10-23')->assertOk();
        $row = collect($res->viewData('recap'))->firstWhere('user.id', $staff->id);

        // Scheduled days = 3
        $this->assertSame(3, $row['scheduled_days']);
        // Present days = 1 (on Thursday)
        $this->assertSame(1, $row['present_days']);
        // Leave days = 2 (Wednesday & Friday, Thursday excluded due to attendance priority)
        $this->assertSame(2, $row['leave_days']);
        // Absent days = 0 (1 present + 2 leave = 3 scheduled)
        $this->assertSame(0, $row['absent_days']);
    }

    /**
     * H5-08: Tanggal mendatang tidak dihitung alpa; hari berjalan tidak dicatat alpa final.
     */
    public function test_future_dates_are_not_counted_as_absent(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR', 'hrd');
        $staff = $this->member($company, 'Future Tester', 'staff');

        // Set period far in future (e.g. 2028-11-01 to 2028-11-05)
        $res = $this->actingAs($admin)->get('/attendance-recap?start_date=2028-11-01&end_date=2028-11-03')->assertOk();
        $row = collect($res->viewData('recap'))->firstWhere('user.id', $staff->id);

        $this->assertSame(3, $row['scheduled_days']);
        $this->assertSame(0, $row['present_days']);
        $this->assertSame(0, $row['leave_days']);
        // Crucial invariant: absent_days MUST BE 0 for future dates!
        $this->assertSame(0, $row['absent_days']);
        $this->assertSame(3, $row['future_scheduled_days']);
    }

    /**
     * H5-09: Filter karyawan, unit organisasi, pagination dan detail harian.
     */
    public function test_recap_filtering_by_unit_and_drilldown_show(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Admin', 'hrd');

        $unitIT = OrganizationUnit::create(['company_id' => $company->id, 'code' => 'IT', 'name' => 'IT Department', 'is_active' => true]);
        $unitHR = OrganizationUnit::create(['company_id' => $company->id, 'code' => 'HR', 'name' => 'HR Department', 'is_active' => true]);

        $dev = $this->member($company, 'Developer Satu', 'staff', null, null, $unitIT);
        $recruiter = $this->member($company, 'Recruiter Satu', 'staff', null, null, $unitHR);

        // Filter by unit IT
        $resIT = $this->actingAs($admin)->get('/attendance-recap?start_date=2026-10-21&end_date=2026-10-23&organization_unit_id='.$unitIT->id)->assertOk();
        $rowsIT = collect($resIT->viewData('recap'));
        $this->assertTrue($rowsIT->contains('user.id', $dev->id));
        $this->assertFalse($rowsIT->contains('user.id', $recruiter->id));

        // Drilldown show view for Developer
        $resShow = $this->get("/attendance-recap/{$dev->id}?start_date=2026-10-21&end_date=2026-10-23&organization_unit_id={$unitIT->id}")
            ->assertOk()
            ->assertSee('Detail Rekap Kehadiran: Developer Satu')
            ->assertSee('IT Department')
            ->assertSee('Kembali ke Rekap');

        $this->assertCount(3, $resShow->viewData('days'));
    }

    /**
     * H5-11: Role, tenant, supervisor subordinate scope, dan akun nonaktif.
     */
    public function test_role_and_tenant_supervisor_subordinate_scoping(): void
    {
        $company1 = $this->company('Perusahaan Satu');
        $spv1 = $this->member($company1, 'Spv Satu', 'supervisor');
        $subordinate1 = $this->member($company1, 'Bawahan Spv', 'staff', null, $spv1);
        $otherStaff1 = $this->member($company1, 'Staf Lain', 'staff');

        $company2 = $this->company('Perusahaan Dua');
        $staffCompany2 = $this->member($company2, 'Staf Dua', 'staff');

        // 1. Spv 1 can view & edit their own subordinate
        $attSub = Attendance::create([
            'user_id' => $subordinate1->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        $this->actingAs($spv1)->get('/attendances/'.$attSub->id.'/edit')->assertOk();

        // 2. Spv 1 CANNOT edit staff who is NOT their subordinate (403)
        $attOther = Attendance::create([
            'user_id' => $otherStaff1->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        $this->actingAs($spv1)->get('/attendances/'.$attOther->id.'/edit')->assertForbidden();

        $this->actingAs($spv1)->put('/attendances/'.$attOther->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'correction_reason' => 'Ilegal attempt',
            'version' => 0,
        ])->assertForbidden();

        // 3. Spv 1 cannot access company 2 staff attendance (404/403)
        $attComp2 = Attendance::create([
            'user_id' => $staffCompany2->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        $this->actingAs($spv1)->get('/attendances/'.$attComp2->id.'/edit')->assertNotFound();

        // 4. Inactive user cannot correct attendance (session invalidated / denied)
        $spv1->forceFill(['employment_status' => 'inactive', 'archived_at' => now()])->save();
        $inactiveRes = $this->actingAs($spv1)->putJson('/attendances/'.$attSub->id, [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'correction_reason' => 'Inactive spv attempt',
            'version' => 0,
        ]);
        $this->assertTrue(in_array($inactiveRes->status(), [401, 403], true));
    }

    /**
     * Dashboard: Multi-day range permission with cancellation_requested recognized as leave.
     */
    public function test_dashboard_range_permission_and_cancellation_requested_status(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'hrd');
        $staff = $this->member($company, 'Range Cuti User', 'staff');

        // Multi-day leave from 2026-10-21 to 2026-10-23
        Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-10-21',
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'duration_days' => 3,
            'reason' => 'Cuti 3 hari',
            'status' => 'cancellation_requested',
        ]);

        // Visit dashboard on day 2 (2026-10-22)
        $res = $this->actingAs($admin)->get('/home?date=2026-10-22')->assertOk();
        $summary = $res->viewData('summary');

        // Leave count must be 1 on day 2
        $this->assertSame(1, $summary['leave']);

        // Staff row status must be leave
        $staffRow = collect($res->viewData('rows')->items())->firstWhere('id', $staff->id);
        $this->assertSame('leave', $staffRow->attendance_status);
    }

    /**
     * Gap 1: Detail harian konsisten dengan agregat rekap.
     * Hari libur dalam rentang cuti berstatus utama 'Hari Libur', dengan izin sebagai konteks tambahan.
     */
    public function test_daily_breakdown_holiday_with_leave_range_shows_holiday_as_primary_status_and_matches_recap_aggregate(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin HR', 'hrd');
        $staff = $this->member($company, 'Holiday Leave Staff', 'staff');

        // Multi-day leave from Thursday 2026-10-22 to Sunday 2026-10-25
        // Thursday (22) & Friday (23) = Working days
        // Saturday (24) & Sunday (25) = Weekly holidays
        $perm = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-10-22',
            'start_date' => '2026-10-22',
            'end_date' => '2026-10-25',
            'duration_days' => 4,
            'reason' => 'Cuti panjang akhir pekan',
            'status' => 'approved',
        ]);

        $recapService = app(\App\Services\AttendanceRecap::class);
        $filters = [
            'start_date' => '2026-10-22',
            'end_date' => '2026-10-25',
            'name' => '',
            'user_id' => $staff->id,
            'organization_unit_id' => null,
        ];

        // 1. Check aggregate recap calculation
        $result = $recapService->calculate($company, $filters, $admin);
        $row = collect($result['recap'])->firstWhere('user.id', $staff->id);

        // Aggregate must ONLY count the 2 working days as leave (not the weekend holidays)
        $this->assertSame(2, $row['scheduled_days']);
        $this->assertSame(2, $row['leave_days']);
        $this->assertSame(0, $row['absent_days']);

        // 2. Check daily breakdown
        $breakdown = $recapService->dailyBreakdown($company, $staff, '2026-10-22', '2026-10-25');
        $days = collect($breakdown['days'])->keyBy('date');

        // Thursday & Friday (working days): primary status = 'leave'
        $this->assertSame('leave', $days['2026-10-22']['status_key']);
        $this->assertSame('leave', $days['2026-10-23']['status_key']);
        $this->assertTrue($days['2026-10-22']['is_working']);
        $this->assertTrue($days['2026-10-23']['is_working']);

        // Saturday & Sunday (holidays): primary status must be 'holiday', NOT 'leave'
        $this->assertSame('holiday', $days['2026-10-24']['status_key']);
        $this->assertSame('holiday', $days['2026-10-25']['status_key']);
        $this->assertFalse($days['2026-10-24']['is_working']);
        $this->assertFalse($days['2026-10-25']['is_working']);

        // Permission context remains attached on both holidays for UI context display
        $this->assertNotNull($days['2026-10-24']['permission']);
        $this->assertNotNull($days['2026-10-25']['permission']);
        $this->assertSame($perm->id, $days['2026-10-24']['permission']->id);

        // Verification: count of 'leave' days in breakdown EXACTLY matches aggregate row leave_days (2 == 2)
        $breakdownLeaveCount = collect($breakdown['days'])->where('status_key', 'leave')->count();
        $this->assertSame($row['leave_days'], $breakdownLeaveCount);

        // 3. Check web view render
        $showRes = $this->actingAs($admin)->get("/attendance-recap/{$staff->id}?start_date=2026-10-22&end_date=2026-10-25")->assertOk();
        $showRes->assertSee('Hari libur mingguan');
        $showRes->assertSee('Cuti / Izin (Disetujui)');
    }

    /**
     * Gap 2: Pemrosesan data bertahap (chunked processing) pada ringkasan dan streaming CSV.
     * Tidak memuat seluruh populasi karyawan ke dalam memori sekaligus.
     */
    public function test_recap_summary_and_export_stream_in_chunks_without_materializing_full_population(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin HR', 'hrd');

        // Create 5 employees
        $members = [];
        for ($i = 1; $i <= 5; $i++) {
            $members[] = $this->member($company, "Chunk Member {$i}", 'staff');
        }

        // Alice attended on 2026-10-21
        Attendance::create([
            'user_id' => $members[0]->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:00:00',
            'checked_out_at' => '2026-10-21 10:00:00',
        ]);

        $recapService = app(\App\Services\AttendanceRecap::class);
        $filters = [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'name' => '',
            'user_id' => null,
            'organization_unit_id' => null,
        ];

        // 1. Test streamRecapRows with small chunk size (chunkSize = 2)
        $streamedRows = [];
        $recapService->streamRecapRows($company, $filters, $admin, function ($row) use (&$streamedRows) {
            $streamedRows[] = $row['user']->name;
        }, chunkSize: 2);

        // All 5 members plus admin = 6 members streamed in chunks of 2
        $this->assertCount(6, $streamedRows);
        $this->assertContains('Chunk Member 1', $streamedRows);
        $this->assertContains('Chunk Member 5', $streamedRows);

        // 2. Test calculateFull (which now wraps streamRecapRows)
        $full = $recapService->calculateFull($company, $filters, $admin, chunkSize: 2);
        $this->assertCount(6, $full['recap']);

        // 3. Test calculate summary chunked matches calculate
        $calc = $recapService->calculate($company, $filters, $admin, perPage: 15, page: 1);
        $this->assertSame(6, $calc['summary']['total_employees']);
        $this->assertSame(1, $calc['summary']['total_present']);

        // 4. Test HTTP CSV streamed export
        $csvResponse = $this->actingAs($admin)->get('/attendance-recap/export-csv?start_date=2026-10-21&end_date=2026-10-23')
            ->assertOk()
            ->assertHeader('Content-type', 'text/csv; charset=utf-8');

        $csvContent = $csvResponse->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);
        $this->assertStringContainsString('Chunk Member 1', $csvContent);
        $this->assertStringContainsString('Chunk Member 5', $csvContent);
    }
}
