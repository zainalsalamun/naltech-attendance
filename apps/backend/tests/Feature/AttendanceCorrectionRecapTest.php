<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCorrectionRecapTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.timezone', 'Asia/Jakarta');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));

        return $app;
    }

    private function company(string $name = 'Kantor QA'): Company
    {
        $company = Company::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'address' => 'Jakarta',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'radius_km' => '0.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]), // Senin - Jumat
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $company;
    }

    private function member(Company $company, string $name, string $role = 'staff', ?Shift $shift = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->forceFill([
            'company_id' => $company->id,
            'shift_id' => $shift?->id,
            'employment_status' => 'active',
        ])->save();

        return $user;
    }

    public function test_recap_rate_excludes_holiday_attendance_and_retains_inactive_history(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR', 'hrd');
        $staff = $this->member($company, 'Historical Staff');
        $staff->forceFill(['joined_on' => '2026-10-23', 'left_on' => '2026-10-26'])->save();
        foreach (['2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26'] as $date) {
            Attendance::create(['user_id' => $staff->id, 'date' => $date, 'time_in' => '08:00:00', 'latlon_in' => '-6.2,106.8']);
        }
        $url = '/attendance-recap?start_date=2026-10-23&end_date=2026-10-26&name=Historical';
        $before = $this->actingAs($admin)->get($url)->assertOk();
        $this->assertSame(100.0, $before->viewData('summary')['attendance_rate']);
        $this->assertSame(4, $before->viewData('summary')['total_present']);
        $this->assertSame(2, $before->viewData('summary')['scheduled_capacity']);
        $staff->forceFill(['employment_status' => 'inactive', 'archived_at' => now()])->save();
        $after = $this->get($url)->assertOk();
        $this->assertSame($before->viewData('summary'), $after->viewData('summary'));
        $this->get(str_replace('/attendance-recap?', '/attendance-recap/export-csv?', $url))
            ->assertOk()->assertStreamed();
        $row = $after->viewData('recap')[0];
        $this->assertSame(2, $row['scheduled_days']);
        $this->assertSame(0, $row['absent_days']);
        $outside = $this->get('/attendance-recap?start_date=2026-10-27&end_date=2026-10-28&name=Historical')->assertOk();
        $this->assertSame(0, $outside->viewData('summary')['total_employees']);
    }

    public function test_recap_limits_inclusive_range_on_page_and_export(): void
    {
        $admin = $this->member($this->company(), 'HR', 'hrd');
        $this->actingAs($admin);
        foreach (['/attendance-recap', '/attendance-recap/export-csv'] as $url) {
            $this->getJson($url.'?start_date=2026-01-01&end_date=2026-03-31')->assertOk();
            foreach (['2026-04-01', '2025-12-31'] as $end) {
                $this->getJson($url.'?start_date=2026-01-01&end_date='.$end)->assertUnprocessable()->assertJsonValidationErrors('end_date');
            }
        }
    }

    public function test_attendance_correction_records_audit_trail_and_displays_on_edit_page(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Admin', 'admin');
        $staff = $this->member($company, 'Budi Staff', 'staff');

        $shift = new Shift(['name' => 'Regular', 'time_in' => '08:00:00', 'time_out' => '17:00:00']);
        $shift->company_id = $company->id;
        $shift->save();
        $staff->update(['shift_id' => $shift->id]);

        $attendance = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '08:30:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:30:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'scheduled_name' => 'Regular',
            'scheduled_start_at' => '2026-10-21 01:00:00',
        ]);

        // 1. Visit edit page before correction: shows empty audit history state
        $this->actingAs($admin)->get('/attendances/'.$attendance->id.'/edit')
            ->assertOk()
            ->assertSee('Koreksi catatan absensi')
            ->assertSee('Belum ada riwayat koreksi untuk catatan ini');

        // 2. Perform audited correction
        $correctionData = [
            'checkin_date' => '2026-10-21',
            'time_in' => '08:00:00',
            'checkout_date' => '2026-10-21',
            'time_out' => '17:15:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'correction_reason' => 'Koreksi mesin fingerprint offline saat masuk',
        ];

        $this->actingAs($admin)->put('/attendances/'.$attendance->id, $correctionData)
            ->assertRedirect('/attendances');

        // 3. Verify attendance updated & audit event saved in DB
        $attendance->refresh();
        $this->assertSame('08:00:00', $attendance->time_in);
        $this->assertSame('17:15:00', $attendance->time_out);

        $this->assertDatabaseHas('attendance_correction_events', [
            'company_id' => $company->id,
            'attendance_id' => $attendance->id,
            'actor_id' => $admin->id,
            'reason' => 'Koreksi mesin fingerprint offline saat masuk',
        ]);

        // 4. Visit edit page after correction: shows audit trail
        $this->actingAs($admin)->get('/attendances/'.$attendance->id.'/edit')
            ->assertOk()
            ->assertSee('Koreksi mesin fingerprint offline saat masuk')
            ->assertSee('HR Admin')
            ->assertSee('08:30:00')
            ->assertSee('08:00:00');
    }

    public function test_dedicated_attendance_corrections_page_scopes_to_company_and_filters(): void
    {
        $company1 = $this->company('Perusahaan Satu');
        $admin1 = $this->member($company1, 'Admin Satu', 'admin');
        $staff1 = $this->member($company1, 'Staff Satu', 'staff');

        $company2 = $this->company('Perusahaan Dua');
        $admin2 = $this->member($company2, 'Admin Dua', 'admin');
        $staff2 = $this->member($company2, 'Staff Dua', 'staff');

        // Correction in company 1
        $att1 = Attendance::create([
            'user_id' => $staff1->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
        ]);
        DB::table('attendance_correction_events')->insert([
            'company_id' => $company1->id,
            'attendance_id' => $att1->id,
            'actor_id' => $admin1->id,
            'reason' => 'Alasan Rahasia Satu',
            'snapshot' => json_encode(['before' => ['time_in' => '09:00'], 'after' => ['time_in' => '08:00']]),
            'created_at' => now(),
        ]);

        // Correction in company 2
        $att2 = Attendance::create([
            'user_id' => $staff2->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'latlon_in' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
        ]);
        DB::table('attendance_correction_events')->insert([
            'company_id' => $company2->id,
            'attendance_id' => $att2->id,
            'actor_id' => $admin2->id,
            'reason' => 'Alasan Rahasia Dua',
            'snapshot' => json_encode(['before' => ['time_in' => '09:00'], 'after' => ['time_in' => '08:00']]),
            'created_at' => now(),
        ]);

        // Admin 1 visits corrections page: sees company 1, does NOT see company 2
        $this->actingAs($admin1)->get('/attendance-corrections')
            ->assertOk()
            ->assertSee('Staff Satu')
            ->assertSee('Alasan Rahasia Satu')
            ->assertDontSee('Staff Dua')
            ->assertDontSee('Alasan Rahasia Dua');

        // Staff user is forbidden
        $this->actingAs($staff1)->get('/attendance-corrections')->assertForbidden();
    }

    public function test_attendance_periodic_recap_and_csv_export(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Manager', 'hrd');
        $staffA = $this->member($company, 'Alice Staff', 'staff');
        $staffB = $this->member($company, 'Bob Staff', 'staff');

        // Wednesday 2026-10-21 to Friday 2026-10-23 (3 scheduled working days)
        // Alice attended 2026-10-21 on time, 2026-10-22 on time
        Attendance::create([
            'user_id' => $staffA->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:00:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'scheduled_start_at' => '2026-10-21 01:00:00',
        ]);
        Attendance::create([
            'user_id' => $staffA->id,
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

        // Bob has approved leave on Friday 2026-10-23
        Permission::create([
            'user_id' => $staffB->id,
            'date_permission' => '2026-10-23',
            'start_date' => '2026-10-23',
            'end_date' => '2026-10-23',
            'duration_days' => 1,
            'reason' => 'Cuti resmi',
            'status' => 'approved',
            'is_approved' => true,
        ]);

        // 1. Visit Recap page
        $response = $this->actingAs($admin)->get('/attendance-recap?start_date=2026-10-21&end_date=2026-10-23')
            ->assertOk()
            ->assertSee('Rekapitulasi Kehadiran Karyawan')
            ->assertSee('Alice Staff')
            ->assertSee('Bob Staff')
            ->assertSee('3 hari') // Scheduled days
            ->assertSee('2 hari') // Alice present days
            ->assertSee('1 hari'); // Bob leave days

        // 2. Export CSV
        $csvResponse = $this->actingAs($admin)->get('/attendance-recap/export-csv?start_date=2026-10-21&end_date=2026-10-23')
            ->assertOk()
            ->assertHeader('Content-type', 'text/csv; charset=utf-8');

        $content = $csvResponse->streamedContent();
        $this->assertStringContainsString('Nama Karyawan', $content);
        $this->assertStringContainsString('Alice Staff', $content);
        $this->assertStringContainsString('Bob Staff', $content);
    }

    public function test_attendance_edit_view_renders_helper_text_and_preview_diff_indicators(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'HR Admin', 'hrd');
        $staff = $this->member($company, 'Staff Member', 'staff');

        $att = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:00:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'scheduled_start_at' => '2026-10-21 01:00:00',
        ]);

        $res = $this->actingAs($admin)->get('/attendances/'.$att->id.'/edit');
        $res->assertOk();
        $res->assertSee('Isi tanggal pulang sesuai kejadian sebenarnya; pada shift malam biasanya hari berikutnya.');
        $res->assertSee('correction-preview-dialog');
        $res->assertSee('Tinjau Koreksi Kehadiran');
        $res->assertSee('badge-diff-in');
        $res->assertSee('badge-diff-out');
        $res->assertSee('badge-diff-dates');
        $res->assertSee('badge-diff-latlon-in');
        $res->assertSee('badge-diff-latlon-out');
    }
}
