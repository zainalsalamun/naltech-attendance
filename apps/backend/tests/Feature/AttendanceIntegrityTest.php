<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\QrAbsen;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        // Never read or migrate the developer's configured database.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.timezone', 'Asia/Jakarta');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
    }

    private function company(array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Jakarta',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None',
        ], $attributes));
    }

    private function employee(?Company $company, string $role = 'staff'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->company_id = $company?->id;
        $user->save();
        return $user;
    }

    private function coordinates(): array
    {
        return ['latitude' => '-6.2', 'longitude' => '106.8'];
    }

    public function test_repeated_checkin_is_rejected_without_creating_another_row(): void
    {
        Sanctum::actingAs($this->employee($this->company()));
        $this->postJson('/api/checkin', $this->coordinates())->assertOk();
        $this->postJson('/api/checkin', $this->coordinates())->assertStatus(409);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_database_rejects_duplicate_daily_attendance(): void
    {
        $user = $this->employee($this->company());
        $data = ['user_id' => $user->id, 'date' => '2026-10-19', 'time_in' => '08:00:00', 'latlon_in' => '-6.2,106.8'];
        Attendance::create($data);
        $this->expectException(QueryException::class);
        Attendance::create($data);
    }

    public function test_checkout_requires_checkin_and_cannot_overwrite_first_checkout(): void
    {
        Sanctum::actingAs($this->employee($this->company()));
        $this->postJson('/api/checkout', $this->coordinates())->assertStatus(400);
        $this->postJson('/api/checkin', $this->coordinates())->assertOk();
        $this->travel(8)->hours();
        $this->postJson('/api/checkout', $this->coordinates())->assertOk();
        $first = Attendance::first()->time_out;
        $this->travel(1)->hours();
        $this->postJson('/api/checkout', $this->coordinates())->assertStatus(409);
        $this->assertSame($first, Attendance::first()->time_out);
    }

    public function test_outside_radius_is_rejected_for_checkin_and_checkout(): void
    {
        Sanctum::actingAs($this->employee($this->company()));
        $outside = ['latitude' => '-7.2', 'longitude' => '106.8'];
        $this->postJson('/api/checkin', $outside)->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
        $this->postJson('/api/checkin', $this->coordinates())->assertOk();
        $this->postJson('/api/checkout', $outside)->assertUnprocessable();
        $this->assertNull(Attendance::first()->time_out);
    }

    public function test_invalid_coordinates_and_company_location_are_rejected(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->employee($company));
        foreach ([['latitude' => 'NaN', 'longitude' => 106], ['latitude' => 91, 'longitude' => 106], ['latitude' => 0, 'longitude' => 181], []] as $data) {
            $this->postJson('/api/checkin', $data)->assertUnprocessable();
        }
        $company->update(['radius_km' => 'invalid']);
        $this->postJson('/api/checkin', $this->coordinates())->assertStatus(409);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_company_is_resolved_from_membership_not_first_row_or_payload(): void
    {
        $other = $this->company(['latitude' => '-7.2']);
        $own = $this->company();
        Sanctum::actingAs($this->employee($own));
        $this->getJson('/api/company')->assertOk()->assertJsonPath('company.id', $own->id);
        $this->postJson('/api/checkin', $this->coordinates() + ['company_id' => $other->id])->assertOk();
        $this->getJson('/api/is-checkin')->assertJson(['checkedin' => true, 'checkedout' => false]);
    }

    public function test_unassigned_user_is_denied_without_falling_back_to_another_company(): void
    {
        $this->company();
        Sanctum::actingAs($this->employee(null));
        $this->getJson('/api/company')->assertForbidden();
        $this->postJson('/api/checkin', $this->coordinates())->assertForbidden();
    }

    public function test_face_mode_cannot_be_bypassed_by_a_client_match_claim(): void
    {
        Sanctum::actingAs($this->employee($this->company(['attendance_type' => 'Face'])));
        $this->postJson('/api/checkin', $this->coordinates() + ['is_match' => true, 'face_embedding' => '1,2,3'])
            ->assertStatus(503);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_qr_is_checked_at_submission_for_date_action_and_company(): void
    {
        $company = $this->company(['attendance_type' => 'QR']);
        $qr = new QrAbsen(['date' => '2026-10-19', 'qr_checkin' => 'IN123', 'qr_checkout' => 'OUT123']);
        $qr->company_id = $company->id;
        $qr->save();
        Sanctum::actingAs($this->employee($company));
        $this->postJson('/api/checkin', $this->coordinates())->assertUnprocessable();
        $this->postJson('/api/checkin', $this->coordinates() + ['qr_code' => 'OUT123'])->assertUnprocessable();
        $this->postJson('/api/checkin', $this->coordinates() + ['qr_code' => 'IN123'])->assertOk();
        $this->postJson('/api/checkout', $this->coordinates() + ['qr_code' => 'IN123'])->assertUnprocessable();
        $this->postJson('/api/checkout', $this->coordinates() + ['qr_code' => 'OUT123'])->assertOk();
        $this->getJson('/api/is-checkin')->assertJson(['checkedin' => true, 'checkedout' => true]);
    }

    public function test_other_company_qr_and_old_qr_cannot_authorize_attendance(): void
    {
        $company = $this->company(['attendance_type' => 'QR']);
        $other = $this->company();
        foreach ([[$other->id, '2026-10-19', 'OTHER'], [$company->id, '2026-10-18', 'OLD']] as [$companyId, $date, $code]) {
            $qr = new QrAbsen(['date' => $date, 'qr_checkin' => $code]);
            $qr->company_id = $companyId;
            $qr->save();
        }
        Sanctum::actingAs($this->employee($company));
        foreach (['OTHER', 'OLD'] as $code) {
            $this->postJson('/api/checkin', $this->coordinates() + ['qr_code' => $code])->assertUnprocessable();
        }
        $this->postJson('/api/check-qr', ['date' => '2026-10-18', 'type_qr' => 'qr_checkin', 'qr_code' => 'OLD'])->assertUnprocessable();
    }

    public function test_admin_cannot_read_or_bind_another_company_resources(): void
    {
        $own = $this->company();
        $other = $this->company();
        $admin = $this->employee($own, 'admin');
        $outsider = $this->employee($other);
        $shift = new Shift(['name' => 'Foreign shift', 'time_in' => '08:00', 'time_out' => '17:00']);
        $shift->company_id = $other->id;
        $shift->save();
        $this->actingAs($admin);
        $this->get('/users/'.$outsider->id.'/edit')->assertNotFound();
        $this->get('/companies/'.$other->id)->assertNotFound();
        $this->put('/companies/'.$other->id, [])->assertNotFound();
        $this->get('/shifts/'.$shift->id.'/edit')->assertNotFound();
    }

    public function test_admin_cannot_assign_a_shift_from_another_company(): void
    {
        $own = $this->company();
        $other = $this->company();
        $admin = $this->employee($own, 'admin');
        $shift = new Shift(['name' => 'Foreign shift', 'time_in' => '08:00', 'time_out' => '17:00']);
        $shift->company_id = $other->id;
        $shift->save();
        $this->actingAs($admin)->postJson('/users', [
            'name' => 'New staff', 'email' => 'new@example.test', 'password' => 'password123',
            'shift_id' => $shift->id,
        ])->assertUnprocessable();
    }

    public function test_employee_cannot_access_admin_management_or_other_profiles(): void
    {
        $company = $this->company();
        $staff = $this->employee($company);
        $other = $this->employee($company);
        $this->actingAs($staff)->get('/users')->assertForbidden();
        Sanctum::actingAs($staff);
        $this->getJson('/api/api-user/'.$other->id)->assertForbidden();
        $this->postJson('/api/api-user/edit', [
            'id' => $other->id, 'name' => 'Changed', 'email' => 'changed@example.test', 'phone' => '0800',
        ])->assertForbidden();
        $this->assertNotSame('Changed', $other->fresh()->name);
    }

    public function test_history_and_shift_listing_do_not_leak_other_company_records(): void
    {
        $own = $this->company();
        $other = $this->company();
        $staff = $this->employee($own);
        $outsider = $this->employee($other);
        Attendance::create(['user_id' => $outsider->id, 'date' => '2026-10-19', 'time_in' => '08:00:00', 'latlon_in' => '0,0']);
        $shift = new Shift(['name' => 'Other company', 'time_in' => '08:00', 'time_out' => '17:00']);
        $shift->company_id = $other->id;
        $shift->save();
        Sanctum::actingAs($staff);
        $this->getJson('/api/api-attendances')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/api-shifts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/is-checkin')->assertJson(['checkedin' => false, 'checkedout' => false]);
        $this->assertNull(app(\App\Support\CompanyContext::class)->id);
    }

    public function test_new_staff_inherits_admin_company_and_cannot_choose_another(): void
    {
        $own = $this->company();
        $other = $this->company();
        $admin = $this->employee($own, 'admin');
        $this->actingAs($admin)->post('/users', [
            'name' => 'New staff', 'email' => 'new@example.test', 'password' => 'password123',
            'company_id' => $other->id, 'role' => 'staff',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'company_id' => $own->id]);
    }

    public function test_membership_migration_backfills_only_an_unambiguous_company(): void
    {
        $company = $this->company();
        $staff = $this->employee(null);
        $migration = require database_path('migrations/2026_10_19_000001_add_company_membership.php');
        $migration->down();
        $migration->up();
        $this->assertSame($company->id, (int) $staff->fresh()->company_id);
    }

    public function test_membership_migration_does_not_guess_between_multiple_companies(): void
    {
        $this->company();
        $this->company();
        $staff = $this->employee(null);
        $migration = require database_path('migrations/2026_10_19_000001_add_company_membership.php');
        $migration->down();
        $migration->up();
        $this->assertNull($staff->fresh()->company_id);
    }

    public function test_unique_migration_stops_on_legacy_duplicates_without_deleting_them(): void
    {
        $user = $this->employee($this->company());
        $migration = require database_path('migrations/2026_10_19_000002_unique_daily_attendance.php');
        $migration->down();
        $data = ['user_id' => $user->id, 'date' => '2026-10-19', 'time_in' => '08:00:00', 'latlon_in' => '0,0'];
        Attendance::create($data);
        Attendance::create($data);
        try {
            $migration->up();
            $this->fail('Migration must reject ambiguous attendance records.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Duplikasi absensi', $e->getMessage());
            $this->assertDatabaseCount('attendances', 2);
        }
    }
}
