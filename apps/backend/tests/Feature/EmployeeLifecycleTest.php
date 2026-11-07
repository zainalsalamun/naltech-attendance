<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\EmployeeLifecycle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeLifecycleTest extends TestCase
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
        return ['name' => $user->name, 'email' => $user->email, 'version' => (int) ($user->fresh()->version ?? 0)];
    }

    public function test_archive_preserves_all_business_history_and_revokes_credentials(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $token = $staff->createToken('fixture')->plainTextToken;
        DB::table('sessions')->insert(['id' => 'fixture-session', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
        Attendance::create(['user_id' => $staff->id, 'date' => '2026-10-20', 'time_in' => '08:00', 'latlon_in' => '-6.2,106.8']);
        Permission::create(['user_id' => $staff->id, 'date_permission' => '2026-10-20', 'reason' => 'Reason']);
        Overtime::create(['user_id' => $staff->id, 'date' => '2026-10-20', 'duration' => 1]);
        Reimbursement::create(['user_id' => $staff->id, 'date' => '2026-10-20', 'description' => 'Reason', 'amount' => '1.25']);
        $this->actingAs($admin)->delete('/users/'.$staff->id, ['lifecycle_reason' => 'Kontrak selesai', 'left_on' => '2026-10-21', 'version' => (int) ($staff->fresh()->version ?? 0)])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'employment_status' => 'inactive']);
        $this->assertNotNull($staff->fresh()->archived_at);
        foreach (['attendances', 'permissions', 'overtimes', 'reimbursements'] as $table) {
            $this->assertDatabaseHas($table, ['user_id' => $staff->id]);
        }
        $this->assertSame(0, $staff->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'fixture-session']);
        $event = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->first();
        $this->assertSame('archived', $event->action);
        $this->assertSame($admin->id, (int) $event->actor_id);
        $this->assertStringNotContainsString('password', $event->after);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $staff->email, 'password' => 'Password123!'])->assertUnauthorized()->assertJsonMissingPath('token');
    }

    public function test_employee_number_normalization_unique_scope_and_archived_reservation(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $this->actingAs($admin)->post('/users', ['name' => 'New', 'email' => 'new@example.test', 'password' => 'Password123!', 'employee_number' => ' emp-001 '])->assertRedirect('/users');
        $staff = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('EMP-001', $staff->employee_number);
        $this->assertNull($staff->joined_on);
        $this->postJson('/users', ['name' => 'Duplicate', 'email' => 'duplicate@example.test', 'password' => 'Password123!', 'employee_number' => 'emp-001'])->assertUnprocessable()->assertJsonValidationErrors('employee_number');
        $this->delete('/users/'.$staff->id, ['lifecycle_reason' => 'Arsip', 'version' => (int) ($staff->fresh()->version ?? 0)])->assertRedirect();
        $this->postJson('/users', ['name' => 'Reuse', 'email' => 'reuse@example.test', 'password' => 'Password123!', 'employee_number' => 'EMP-001'])->assertUnprocessable();
        $other = $this->company();
        $this->actingAs($this->user($other, 'admin'))->post('/users', ['name' => 'Other', 'email' => 'other@example.test', 'password' => 'Password123!', 'employee_number' => 'emp-001'])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['company_id' => $other->id, 'employee_number' => 'EMP-001']);
    }

    public function test_status_reason_dates_self_archive_last_admin_and_foreign_company_guards(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $foreignAdmin = $this->user($this->company(), 'admin');
        $this->actingAs($admin)->putJson('/users/'.$staff->id, $this->fields($staff) + ['joined_on' => '2026-10-20', 'left_on' => '2026-10-19', 'employment_status' => 'inactive', 'lifecycle_reason' => 'Exit'])->assertUnprocessable()->assertJsonValidationErrors('left_on');
        $this->putJson('/users/'.$staff->id, $this->fields($staff) + ['employment_status' => 'inactive'])->assertUnprocessable()->assertJsonValidationErrors('lifecycle_reason');
        $this->deleteJson('/users/'.$admin->id, ['lifecycle_reason' => 'Self', 'version' => (int) ($admin->fresh()->version ?? 0)])->assertUnprocessable();
        $this->putJson('/users/'.$admin->id, $this->fields($admin) + ['role' => 'staff'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->deleteJson('/users/'.$foreignAdmin->id, ['lifecycle_reason' => 'Other', 'version' => 0])->assertNotFound();
        $this->assertTrue($admin->fresh()->isEmploymentActive());
        $this->actingAs($this->user($company, 'supervisor'))->deleteJson('/users/'.$admin->id, ['lifecycle_reason' => 'Escalate'])->assertForbidden();
    }

    public function test_inactive_accounts_cannot_use_web_login_or_existing_bearer_on_any_private_api(): void
    {
        $staff = $this->user($this->company());
        $token = $staff->createToken('fixture')->plainTextToken;
        $staff->forceFill(['employment_status' => 'inactive'])->save();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Password123!'])->assertSessionHasErrors();
        $this->assertGuest('web');
        foreach (['/api/user', '/api/company', '/api/api-permissions', '/api/api-shifts'] as $path) {
            $this->app['auth']->forgetGuards();
            $this->getJson($path, ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        }
        $this->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8], ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_old_web_session_stays_invalid_after_reactivation_without_revoking_new_tokens(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $this->post('/login', ['email' => $staff->email, 'password' => 'Password123!'])->assertRedirect();
        $this->get('/home')->assertOk();
        $service = app(EmployeeLifecycle::class);
        $service->save($admin, $staff, ['employment_status' => 'inactive', 'version' => (int) ($staff->fresh()->version ?? 0)], 'Nonaktif');
        $service->save($admin, $staff, ['employment_status' => 'active', 'left_on' => null, 'version' => (int) ($staff->fresh()->version ?? 0)], 'Aktif kembali');
        $newToken = $staff->fresh()->createToken('new-fixture');
        $this->get('/home')->assertRedirect('/login');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $newToken->accessToken->id]);
        $this->post('/login', ['email' => $staff->email, 'password' => 'Password123!'])->assertRedirect();
        $this->get('/home')->assertOk();
        $this->assertSame(2, (int) $staff->fresh()->auth_epoch);
    }

    public function test_filters_history_and_dashboard_preserve_archive_visibility(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $staff->forceFill(['employee_number' => 'EMP-HISTORY'])->save();
        $this->actingAs($admin)->delete('/users/'.$staff->id, ['lifecycle_reason' => 'Kontrak selesai', 'version' => (int) ($staff->fresh()->version ?? 0)])->assertRedirect();
        $this->get('/users')->assertOk()->assertViewHas('users', fn ($rows) => ! $rows->contains('id', $staff->id));
        $this->get('/users?status=archived&name=EMP-HISTORY')->assertOk()->assertViewHas('users', fn ($rows) => $rows->total() === 1)->assertSee('Diarsipkan');
        $this->get('/users/'.$staff->id.'/edit')->assertOk()->assertSee('Kontrak selesai')->assertSee('Riwayat kepegawaian');
        $this->get('/home')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
        $this->get('/home?date=2020-01-01')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
    }

    public function test_unmigrated_employee_routes_fail_closed_before_binding(): void
    {
        $admin = $this->user($this->company(), 'admin');
        Schema::drop('employee_lifecycle_events');
        $this->actingAs($admin)->getJson('/users/999999/edit')->assertStatus(503);
        $this->getJson('/users')->assertStatus(503);
    }

    public function test_migration_keeps_legacy_dates_and_numbers_unknown_and_accounts_active(): void
    {
        $company = $this->company();
        $migration = require database_path('migrations/2026_10_21_000001_add_employee_lifecycle.php');
        $migration->down();
        $legacy = $this->user($company);
        $migration->up();
        $this->assertTrue($legacy->fresh()->isEmploymentActive());
        $this->assertNull($legacy->fresh()->employee_number);
        $this->assertNull($legacy->fresh()->joined_on);
        $this->assertNull($legacy->fresh()->left_on);
        $this->assertDatabaseCount('employee_lifecycle_events', 0);
    }
}
