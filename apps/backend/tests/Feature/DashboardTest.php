<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function company(): Company
    {
        return Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Jakarta',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);
    }

    private function member(Company $company, string $name, string $role = 'staff', ?Shift $shift = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->forceFill(['company_id' => $company->id, 'shift_id' => $shift?->id])->save();

        return $user;
    }

    public function test_summary_filters_and_requests_are_scoped_to_active_company(): void
    {
        $company = $this->company();
        $shift = new Shift(['name' => 'Pagi', 'time_in' => '08:00:00', 'time_out' => '17:00:00']);
        $shift->company_id = $company->id;
        $shift->save();
        $admin = $this->member($company, 'Admin', 'admin');
        $ontime = $this->member($company, 'Tepat', shift: $shift);
        $late = $this->member($company, 'Terlambat', shift: $shift);
        $review = $this->member($company, 'Tanpa shift');
        $leave = $this->member($company, 'Izin');
        $outsider = $this->member($this->company(), 'Rahasia perusahaan lain');
        foreach ([[$ontime, '08:00:00'], [$late, '08:01:00'], [$review, '07:00:00'], [$outsider, '09:00:00']] as [$user,$time]) {
            Attendance::create(['user_id' => $user->id, 'date' => '2026-10-19', 'time_in' => $time, 'latlon_in' => '-6.2,106.8']);
        }
        Permission::create(['user_id' => $leave->id, 'date_permission' => '2026-10-19', 'reason' => 'Cuti', 'is_approved' => true]);
        foreach ([$late, $outsider] as $user) {
            Permission::create(['user_id' => $user->id, 'date_permission' => '2026-10-20', 'reason' => 'Permohonan', 'is_approved' => false]);
            Overtime::create(['user_id' => $user->id, 'date' => '2026-10-19', 'duration' => 60, 'description' => 'Tugas', 'status' => 'pending']);
        }
        $this->actingAs($admin)->get('/home?date=2026-10-19')->assertOk()
            ->assertViewHas('summary', ['total' => 5, 'present' => 3, 'ontime' => 1, 'late' => 1, 'absent' => 1, 'leave' => 1, 'review' => 1, 'off' => 0])
            ->assertViewHas('permissionCount', 1)->assertViewHas('overtimeCount', 1)
            ->assertDontSee('Rahasia perusahaan lain');
        $this->get('/home?date=2026-10-19&filter=late&q=Terlambat')->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $late->id)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 5);
        $this->get('/home?date=2026-10-18&filter=absent')->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 5);
    }

    public function test_staff_only_sees_own_data_and_invalid_input_is_rejected(): void
    {
        $company = $this->company();
        $staff = $this->member($company, 'Saya');
        $this->member($company, 'Rekan rahasia');
        $this->actingAs($staff)->get('/home')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1)
            ->assertDontSee('Rekan rahasia')->assertDontSee('href="'.route('users.index').'"', false);
        $this->getJson('/home?date=invalid&filter=invalid')->assertUnprocessable();
        $staff->forceFill(['company_id' => null])->save();
        $this->get('/home')->assertForbidden();
    }

    public function test_pagination_preserves_filters_and_empty_results_keep_summary(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        for ($i = 1; $i <= 25; $i++) {
            $this->member($company, sprintf('Anggota %02d', $i));
        }
        $this->actingAs($admin)->get('/home?date=2026-10-19&filter=absent&q=Anggota&page=2')
            ->assertOk()->assertViewHas('rows', function ($rows) {
                parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);

                return $rows->total() === 25 && $rows->count() === 8 &&
                    $rows->first()->name === 'Anggota 09' &&
                    $query === ['date' => '2026-10-19', 'filter' => 'absent', 'q' => 'Anggota', 'page' => '3'];
            })->assertViewHas('summary', fn ($summary) => $summary['total'] === 26);
        $this->get('/home?q=TidakDitemukan')->assertOk()->assertSee('Tidak ada hasil')
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 26);
    }

    public function test_overnight_and_incomplete_shifts_require_review_and_checkin_takes_priority_over_leave(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        foreach ([['22:00:00', '06:00:00'], ['08:00:00', '08:00:00']] as [$start, $end]) {
            $shift = new Shift(['name' => 'Perlu tinjau', 'time_in' => $start, 'time_out' => $end]);
            $shift->company_id = $company->id;
            $shift->save();
            $member = $this->member($company, 'Anggota', shift: $shift);
            Attendance::create(['user_id' => $member->id, 'date' => '2026-10-19', 'time_in' => $start, 'latlon_in' => '0,0']);
            Permission::create(['user_id' => $member->id, 'date_permission' => '2026-10-19', 'reason' => 'Cuti', 'is_approved' => true]);
        }
        $this->actingAs($admin)->get('/home?date=2026-10-19')->assertOk()
            ->assertViewHas('summary', ['total' => 3, 'present' => 2, 'ontime' => 0, 'late' => 0, 'absent' => 1, 'leave' => 0, 'review' => 2, 'off' => 0]);
    }

    public function test_approval_updates_dashboard_counts_and_cannot_cross_company(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $member = $this->member($company, 'Anggota');
        $outsider = $this->member($this->company(), 'Luar');
        $permission = Permission::create(['user_id' => $member->id, 'date_permission' => '2026-10-19', 'reason' => 'Cuti', 'is_approved' => false]);
        $foreign = Permission::create(['user_id' => $outsider->id, 'date_permission' => '2026-10-19', 'reason' => 'Cuti', 'is_approved' => false]);
        $overtime = Overtime::create(['user_id' => $member->id, 'date' => '2026-10-19', 'duration' => 60, 'status' => 'pending']);
        $this->actingAs($admin);
        $this->put('/permissions/'.$permission->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Disetujui'])->assertRedirect();
        $this->post('/submission-decisions/overtime/'.$overtime->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Disetujui'])
            ->assertRedirect();
        $this->get('/home?date=2026-10-19')->assertOk()->assertViewHas('permissionCount', 0)
            ->assertViewHas('overtimeCount', 0)->assertViewHas('summary', fn ($s) => $s['leave'] === 1);
        $this->get('/permissions/'.$foreign->id)->assertNotFound();
        $this->put('/permissions/'.$foreign->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Foreign'])->assertNotFound();
        $this->putJson('/permissions/'.$permission->id, ['is_approved' => 'invalid'])->assertUnprocessable();
        $this->actingAs($member)->put('/permissions/'.$permission->id, ['is_approved' => 0])->assertForbidden();
        $this->assertEquals(1, $permission->fresh()->is_approved);
    }

    public function test_web_approval_requires_csrf_token(): void
    {
        $admin = $this->member($this->company(), 'Admin', 'admin');
        // Laravel bypasses CSRF automatically in testing; exercise its normal web behavior.
        $this->app['env'] = 'local';
        $this->actingAs($admin)->put('/permissions/1', ['is_approved' => 1])->assertStatus(419);
        $this->put('/overtimes/1', ['date' => '2026-10-19', 'duration' => 60, 'status' => 'approved'])->assertStatus(419);
    }

    public function test_attendance_workspace_filters_pagination_and_export_share_company_scope(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $member = $this->member($company, 'Anggota');
        $outsider = $this->member($this->company(), 'Rahasia luar');
        foreach (range(1, 12) as $day) {
            Attendance::create(['user_id' => $member->id, 'date' => sprintf('2026-10-%02d', $day), 'time_in' => '08:00:00', 'latlon_in' => '0,0']);
        }
        Attendance::create(['user_id' => $outsider->id, 'date' => '2026-10-01', 'time_in' => '08:00:00', 'latlon_in' => '0,0']);
        $this->actingAs($admin)->get('/attendances?name=Anggota&page=2')->assertOk()
            ->assertSee('Catatan kehadiran')->assertDontSee('Rahasia luar')
            ->assertViewHas('attendances', fn ($rows) => $rows->total() === 12 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'name=Anggota'));
        $this->get('/attendances?name=Anggota&date=2026-10-01')->assertOk()
            ->assertViewHas('attendances', fn ($rows) => $rows->total() === 1);
        $export = $this->get('/attendances/export-csv?name=Anggota&date=2026-10-01')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-01', $export);
        $this->assertStringNotContainsString('2026-10-02', $export);
        $this->assertStringNotContainsString('Rahasia luar', $export);
        $this->get('/attendances?name=TidakDitemukan')->assertOk()->assertSee('Tidak ada catatan kehadiran');
        $this->getJson('/attendances?date=invalid')->assertUnprocessable();
        $this->getJson('/attendances/export-csv?date=invalid')->assertUnprocessable();
        $record = Attendance::where('user_id', $member->id)->first();
        $this->get('/attendances/'.$record->id.'/edit')->assertOk()->assertSee('Simpan perubahan');
        $this->put('/attendances/'.$record->id, ['time_in' => '08:30:00', 'latlon_in' => '-6.2,106.8'])
            ->assertRedirect(route('attendances.index'));
        $this->assertSame('08:30:00', $record->fresh()->time_in);
        $this->put('/attendances/'.$record->id, ['time_in' => '', 'latlon_in' => ''])
            ->assertSessionHasErrors(['time_in', 'latlon_in']);
        $foreignRecord = Attendance::where('user_id', $outsider->id)->first();
        $this->get('/attendances/'.$foreignRecord->id.'/edit')->assertNotFound();
        $this->delete('/attendances/'.$foreignRecord->id)->assertNotFound();
        $this->delete('/attendances/'.$record->id)->assertRedirect(route('attendances.index'));
        $this->assertDatabaseMissing('attendances', ['id' => $record->id]);
        $this->actingAs($member)->get('/attendances')->assertForbidden();
    }
}
