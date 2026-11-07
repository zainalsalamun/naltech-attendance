<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EffectiveShiftTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));

        return $app;
    }

    private function company(): Company
    {
        $company = Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Fixture',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        return $company;
    }

    private function user(Company $company, string $role = 'staff'): User
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'Password123!']);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function fields(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email];
    }

    protected function tearDown(): void
    {
        \Carbon\CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function clock(string $date): void
    {
        $time = \Carbon\Carbon::parse($date, 'Asia/Jakarta');
        $this->travelTo($time);
        \Carbon\CarbonImmutable::setTestNow($time);
    }

    private function shift(Company $company, string $in = '22:00:00', string $out = '06:00:00')
    {
        $s = new \App\Models\Shift(['name' => 'Malam', 'time_in' => $in, 'time_out' => $out]);
        $s->company_id = $company->id;
        $s->save();

        return $s;
    }

    private function assign(User $admin, User $owner, $shift, string $date = '2026-10-21', int $expected = 0)
    {
        return $this->actingAs($admin)->postJson('/effective-shifts', ['user_id' => $owner->id, 'shift_id' => $shift?->id, 'effective_from' => $date, 'expected_id' => $expected, 'reason' => 'Rotasi terjadwal']);
    }

    private function position(): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8];
    }

    public function test_assignments_are_versioned_scoped_and_copy_master_times(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $night = $this->shift($company);
        $day = $this->shift($company, '08:00:00', '17:00:00');
        $owner->forceFill(['shift_id' => $day->id])->save();
        $this->assign($admin, $owner, $night, '2026-10-22')->assertRedirect();
        $service = app(\App\Services\EffectiveShift::class);
        $this->assertSame('08:00:00', $service->schedule($owner, '2026-10-21')['scheduled_in']);
        $this->assertSame('22:00:00', $service->schedule($owner, '2026-10-22')['scheduled_in']);
        $this->assertNull($service->schedule($owner, '2026-10-20')['scheduled_in']);
        $night->update(['time_in' => '23:00:00']);
        $this->assertSame('22:00:00', $service->schedule($owner, '2026-10-22')['scheduled_in']);
        $this->assign($admin, $owner, $night, '2026-10-22')->assertConflict();
        $last = (int) \Illuminate\Support\Facades\DB::table('employee_shift_assignments')->where('user_id', $owner->id)->max('id');
        $this->assign($admin, $owner, null, '2026-10-22', $last)->assertRedirect();
        $this->assertNull($service->schedule($owner, '2026-10-22')['scheduled_in']);
        $this->assign($admin, $owner, $night, '2026-10-20', $last + 1)->assertUnprocessable();
        $foreign = $this->user($this->company());
        $this->assign($admin, $foreign, $night)->assertNotFound();
        $supervisor = $this->user($company, 'supervisor');
        $this->assign($supervisor, $owner, $night)->assertForbidden();
        $this->actingAs($supervisor)->get('/effective-shifts?user_id='.$owner->id)->assertOk()->assertDontSee('Simpan jadwal efektif');
        $this->actingAs($admin)->deleteJson('/shifts/'.$night->id)->assertConflict();
        $this->putJson('/work-calendar/timezone', ['timezone' => 'Asia/Jayapura'])->assertUnprocessable();
    }

    public function test_overnight_checkout_uses_original_workday_and_snapshot_lateness(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'hrd');
        $owner = $this->user($company);
        $night = $this->shift($company);
        $this->assign($admin, $owner, $night)->assertRedirect();
        $this->clock('2026-10-21 22:05');
        $this->actingAs($owner)->postJson('/api/checkin', $this->position())->assertOk()->assertJsonPath('attendance.date', '2026-10-21')->assertJsonPath('attendance.scheduled_in', '22:00:00');
        $row = \App\Models\Attendance::firstOrFail();
        $this->assertSame('Terlambat', $row->timing_label);
        $night->update(['time_in' => '23:00:00', 'name' => 'Master diganti']);
        $this->clock('2026-10-22 01:00');
        $this->getJson('/api/is-checkin')->assertOk()->assertJsonPath('attendance_date', '2026-10-21')->assertJsonPath('qr_date', '2026-10-22')->assertJsonPath('checkedin', true)->assertJsonPath('checkedout', false)->assertJsonPath('schedule.scheduled_in', '22:00:00');
        $this->postJson('/api/checkin', $this->position())->assertConflict();
        $this->assign($admin, $owner, $night, '2026-10-21', 1)->assertUnprocessable();
        $this->clock('2026-10-22 06:00');
        $this->actingAs($owner)->postJson('/api/checkout', $this->position())->assertOk()->assertJsonPath('attendance.date', '2026-10-21')->assertJsonPath('attendance.time_out', '06:00:00')->assertJsonPath('attendance.checked_out_at', '2026-10-21 23:00:00');
        $this->postJson('/api/checkout', $this->position())->assertConflict();
        $this->assertDatabaseCount('attendances', 1);
        $this->actingAs($admin)->get('/home?date=2026-10-21')->assertOk()->assertViewHas('summary', fn ($s) => $s['late'] === 1);
        $this->get('/attendances')->assertOk()->assertSee('Malam')->assertSee('2026-10-22');
    }

    public function test_after_midnight_first_checkin_has_previous_anchor_and_utc_comparison(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $this->assign($admin, $owner, $this->shift($company))->assertRedirect();
        $this->clock('2026-10-22 01:00');
        $this->actingAs($owner)->postJson('/api/checkin', $this->position())->assertOk()->assertJsonPath('attendance.date', '2026-10-21')->assertJsonPath('attendance.time_in', '01:00:00');
        $this->assertSame('Terlambat', \App\Models\Attendance::first()->timing_label);
    }

    public function test_stale_session_requires_audited_correction_and_does_not_allow_another_checkin(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $this->assign($admin, $owner, $this->shift($company))->assertRedirect();
        $this->clock('2026-10-21 22:00');
        $this->actingAs($owner)->postJson('/api/checkin', $this->position())->assertOk();
        $row = \App\Models\Attendance::first();
        $this->clock('2026-10-22 23:00');
        $this->getJson('/api/is-checkin')->assertOk()->assertJsonPath('actionable', false);
        $this->postJson('/api/checkout', $this->position())->assertConflict();
        $this->postJson('/api/checkin', $this->position())->assertConflict();
        $correction = ['time_in' => '22:00', 'time_out' => '06:00', 'checkin_date' => '2026-10-21', 'checkout_date' => '2026-10-22', 'latlon_in' => '-6.2,106.8', 'latlon_out' => '-6.2,106.8', 'correction_reason' => 'Memperbaiki sesi terlupa'];
        $this->actingAs($admin)->putJson('/attendances/'.$row->id, $correction)->assertRedirect();
        $this->assertDatabaseCount('attendance_correction_events', 1);
        $this->assertSame('22:00:00', $row->fresh()->scheduled_in);
        $this->deleteJson('/attendances/'.$row->id)->assertConflict();
        $this->actingAs($owner)->postJson('/api/checkin', $this->position())->assertOk()->assertJsonPath('attendance.date', '2026-10-22');
    }

    public function test_overnight_qr_uses_current_company_date_and_keeps_location_validation(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $company->forceFill(['attendance_type' => 'QR'])->save();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $this->assign($admin, $owner, $this->shift($company))->assertRedirect();
        foreach (['2026-10-21', '2026-10-22'] as $date) {
            $qr = new \App\Models\QrAbsen(['date' => $date, 'qr_checkin' => 'in-'.$date, 'qr_checkout' => 'out-'.$date]);
            $qr->company_id = $company->id;
            $qr->save();
        }
        $this->clock('2026-10-21 22:00');
        $this->actingAs($owner)->postJson('/api/checkin', $this->position() + ['qr_code' => 'in-2026-10-21'])->assertOk();
        $this->clock('2026-10-22 06:00');
        $this->postJson('/api/checkout', $this->position() + ['qr_code' => 'out-2026-10-21'])->assertUnprocessable();
        $this->postJson('/api/checkout', ['latitude' => 0, 'longitude' => 0, 'qr_code' => 'out-2026-10-22'])->assertUnprocessable();
        $this->postJson('/api/checkout', $this->position() + ['qr_code' => 'out-2026-10-22'])->assertOk();
    }

    public function test_daylight_saving_gaps_and_ambiguous_times_fail_closed(): void
    {
        $this->clock('2026-03-01 10:00');
        $company = $this->company();
        $company->forceFill(['timezone' => 'America/New_York'])->save();
        $owner = $this->user($company);
        $owner->forceFill(['shift_id' => $this->shift($company, '02:30:00', '10:00:00')->id])->save();
        try {
            app(\App\Services\EffectiveShift::class)->schedule($owner, '2026-03-08');
            $this->fail('DST gap must be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $owner->forceFill(['shift_id' => $this->shift($company, '01:30:00', '10:00:00')->id])->save();
        try {
            app(\App\Services\EffectiveShift::class)->schedule($owner, '2026-11-01');
            $this->fail('DST overlap must be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_web_form_stale_expected_id_redirects_with_conflict_preserving_inputs_and_zero_mutation(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $night = $this->shift($company);

        // First assignment by another admin changes latest assignment ID to 1
        $this->actingAs($admin)->post('/effective-shifts', [
            'user_id' => $owner->id,
            'shift_id' => $night->id,
            'effective_from' => '2026-10-22',
            'expected_id' => 0,
            'reason' => 'Penugasan pertama',
        ])->assertRedirect();

        $countBefore = \Illuminate\Support\Facades\DB::table('employee_shift_assignments')->where('user_id', $owner->id)->count();
        $this->assertSame(2, $countBefore); // 1 baseline + 1 new target

        // Second web submission uses stale expected_id = 0
        $response = $this->actingAs($admin)->from('/effective-shifts?user_id='.$owner->id)->post('/effective-shifts', [
            'user_id' => $owner->id,
            'shift_id' => $night->id,
            'effective_from' => '2026-10-23',
            'expected_id' => 0, // stale!
            'reason' => 'Alasan yang tidak boleh hilang',
        ]);

        $response->assertRedirect('/effective-shifts?user_id='.$owner->id);
        $response->assertSessionHas('conflict', fn ($msg) => str_contains($msg, 'konflik versi'));
        $response->assertSessionHas('_old_input', fn ($old) => 
            ($old['reason'] ?? '') === 'Alasan yang tidak boleh hilang' &&
            ($old['effective_from'] ?? '') === '2026-10-23' &&
            (int)($old['shift_id'] ?? 0) === $night->id
        );

        // Zero mutations on conflict
        $countAfter = \Illuminate\Support\Facades\DB::table('employee_shift_assignments')->where('user_id', $owner->id)->count();
        $this->assertSame($countBefore, $countAfter);
    }

    public function test_effective_shifts_index_renders_preview_dialog_and_modal_attributes(): void
    {
        $this->clock('2026-10-21 10:00');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $owner = $this->user($company);
        $night = $this->shift($company, '22:00:00', '06:00:00');

        $response = $this->actingAs($admin)->get('/effective-shifts?user_id='.$owner->id);
        $response->assertOk();
        $response->assertSee('shift-assignment-preview-dialog');
        $response->assertSee('Tinjau Penugasan Jadwal Efektif');
        $response->assertSee('Dampak pada Sesi & Tanggal Berlaku', false);
        $response->assertSee('Kembali mengedit');
        $response->assertSee('Konfirmasi simpan jadwal');
        $response->assertSee('btn-open-preview');
        $response->assertSee('data-cross="1"', false);
        $response->assertSee('Tanpa shift');
        $response->assertSee('Sebelum (Jadwal Berlaku pada Tanggal Target)');
        $response->assertSee('Pembanding penugasan baru adalah jadwal yang berlaku pada tanggal target sebelum perubahan, termasuk jika sudah ada penugasan pada tanggal tersebut.');
        $response->assertSee('preview-before-name');
        $response->assertSee('Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in.');
    }
}
