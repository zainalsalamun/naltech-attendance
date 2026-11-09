<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class WorkCalendarTest extends TestCase
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
        return Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Fixture',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);
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

    public function test_calendar_unknown_effective_rules_and_date_overrides_are_scoped(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 10:00:00', 'UTC'));
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $calendar = app(\App\Services\WorkCalendar::class);
        $this->assertNull($calendar->day($company, '2026-10-20')['working']);
        $this->actingAs($admin)->post('/work-calendar/rules', ['effective_from' => '2026-10-21', 'weekdays' => ['1', '2', '3', '4', '5'], 'confirm_calendar' => '1'])->assertRedirect();
        $this->assertTrue($calendar->day($company, '2026-10-21')['working']);
        $this->assertFalse($calendar->day($company, '2026-10-24')['working']);
        $this->post('/work-calendar/rules', ['effective_from' => '2026-10-26', 'weekdays' => [6, 7], 'confirm_calendar' => 1])->assertRedirect();
        $this->assertFalse($calendar->day($company, '2026-10-26')['working']);
        $this->assertTrue($calendar->day($company, '2026-10-31')['working']);
        $this->put('/work-calendar/day', ['date' => '2026-10-24', 'is_working' => 1, 'label' => 'Pengganti'])->assertRedirect();
        $this->assertTrue($calendar->day($company, '2026-10-24')['working']);
        $this->put('/work-calendar/day', ['date' => '2026-10-24', 'is_working' => 0, 'label' => 'Libur kantor'])->assertRedirect();
        $this->assertSame('Libur kantor', $calendar->day($company, '2026-10-24')['label']);
        $this->assertDatabaseCount('work_calendar_days', 1);
        $foreign = $this->company();
        $this->assertNull($calendar->day($foreign, '2026-10-24')['working']);
        $this->get('/work-calendar?month=2026-10')->assertOk()->assertSee('Libur kantor');
    }

    public function test_validation_roles_dates_and_missing_schema(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 10:00:00', 'UTC'));
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $this->actingAs($admin)->postJson('/work-calendar/rules', ['effective_from' => '2026-10-20', 'weekdays' => [1], 'confirm_calendar' => 1])->assertUnprocessable();
        $this->postJson('/work-calendar/rules', ['effective_from' => '2026-10-21', 'weekdays' => [0, 8], 'confirm_calendar' => 1])->assertUnprocessable();
        $this->postJson('/work-calendar/rules', ['effective_from' => '2026-10-21', 'weekdays' => [1, 1], 'confirm_calendar' => 1])->assertUnprocessable();
        $this->postJson('/work-calendar/rules', ['effective_from' => '2026-10-21'])->assertUnprocessable();
        $this->post('/work-calendar/rules', ['effective_from' => '2026-10-21', 'confirm_calendar' => 1])->assertRedirect();
        $this->assertFalse(app(\App\Services\WorkCalendar::class)->day($company, '2026-10-21')['working']);
        $this->postJson('/work-calendar/rules', ['effective_from' => '2026-10-21', 'confirm_calendar' => 1])->assertUnprocessable();
        $this->putJson('/work-calendar/day', ['date' => '2026-10-20', 'is_working' => 0, 'label' => 'Backdate'])->assertUnprocessable();
        $this->putJson('/work-calendar/timezone', ['timezone' => 'Invalid/Zone'])->assertUnprocessable();
        $this->actingAs($this->user($company, 'supervisor'))->get('/work-calendar')->assertOk();
        $this->putJson('/work-calendar/timezone', ['timezone' => 'UTC'])->assertForbidden();
        $this->postJson('/work-calendar/rules', ['effective_from' => '2026-10-21', 'confirm_calendar' => 1])->assertForbidden();
        $this->putJson('/work-calendar/day', ['date' => '2026-10-21', 'is_working' => 0, 'label' => 'No'])->assertForbidden();
        $this->actingAs($this->user($company))->get('/work-calendar')->assertForbidden();
        $this->actingAs($admin);
        \Illuminate\Support\Facades\Schema::drop('work_calendar_days');
        $this->getJson('/work-calendar')->assertStatus(503);
    }

    public function test_company_timezone_drives_api_date_qr_and_dashboard_without_changing_app_timezone(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $original = config('app.timezone');
        $this->actingAs($admin)->put('/work-calendar/timezone', ['timezone' => 'Asia/Jayapura'])->assertRedirect();
        $this->get('/home')->assertOk()->assertViewHas('date', '2026-10-22')->assertViewHas('timezone', 'Asia/Jayapura');
        $this->actingAs($staff)->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8])->assertOk();
        $this->assertDatabaseHas('attendances', ['user_id' => $staff->id, 'date' => '2026-10-22', 'time_in' => '03:00:00']);
        $this->getJson('/api/is-checkin')->assertOk()->assertJson(['checkedin' => true]);
        $this->actingAs($admin)->putJson('/work-calendar/timezone', ['timezone' => 'UTC'])->assertUnprocessable();
        $this->put('/work-calendar/timezone', ['timezone' => 'Asia/Jayapura'])->assertRedirect();
        $this->assertSame($original, config('app.timezone'));
        $this->get('/home?date=2026-10-22')->assertOk();
        $company->update(['attendance_type' => 'QR']);
        $other = $this->user($company);
        $qr = new \App\Models\QrAbsen(['date' => '2026-10-22', 'qr_checkin' => 'fixture-in', 'qr_checkout' => 'fixture-out']);
        $qr->company_id = $company->id;
        $qr->save();
        $this->actingAs($other)->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8, 'qr_code' => 'fixture-in'])->assertOk();
    }

    public function test_holiday_does_not_count_absence_and_present_records_remain_visible(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 10:00:00', 'UTC'));
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $this->actingAs($admin)->put('/work-calendar/day', ['date' => '2026-10-21', 'is_working' => 0, 'label' => 'Libur kantor'])->assertRedirect();
        $this->get('/home')->assertOk()->assertViewHas('summary', fn ($s) => $s['absent'] === 0 && $s['off'] === 2)->assertSee('Libur kantor');
        $this->actingAs($staff)->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8])->assertOk();
        $this->actingAs($admin)->get('/home')->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['off'] === 1 && $s['absent'] === 0);
        $this->get('/home?filter=absent')->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        $this->get('/home?date=2026-10-20')->assertViewHas('calendarDay', fn ($day) => $day['working'] === null);
    }
}
