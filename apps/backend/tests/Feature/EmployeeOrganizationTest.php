<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeOrganizationTest extends TestCase
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

    public function test_units_are_scoped_unique_and_role_protected(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $this->actingAs($admin)->post('/units', ['code' => ' eng ', 'name' => 'Engineering', 'is_active' => 1])->assertRedirect('/units');
        $unit = \App\Models\OrganizationUnit::firstOrFail();
        $this->assertSame('ENG', $unit->code);
        $this->postJson('/units', ['code' => 'ENG', 'name' => 'Duplicate', 'is_active' => 1])->assertUnprocessable()->assertJsonValidationErrors('code');
        $foreign = $this->user($this->company(), 'admin');
        $this->actingAs($foreign)->get('/units/'.$unit->id.'/edit')->assertNotFound();
        $this->putJson('/units/'.$unit->id, ['code' => 'ENG', 'name' => 'Foreign', 'is_active' => 0])->assertNotFound();
        $this->post('/units', ['code' => 'ENG', 'name' => 'Other company', 'is_active' => 1])->assertRedirect('/units');
        $this->get('/units')->assertViewHas('units', fn ($units) => $units->total() === 1);
        $this->actingAs($this->user($company, 'supervisor'))->get('/units')->assertOk();
        $this->postJson('/units', ['code' => 'OPS', 'name' => 'Ops', 'is_active' => 1])->assertForbidden();
        $this->actingAs($this->user($company))->get('/units')->assertForbidden();
    }

    public function test_mapping_preserves_legacy_department_and_audits_ids(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $staff->update(['department' => 'Departemen lama']);
        $unit = new \App\Models\OrganizationUnit(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
        $unit->company_id = $company->id;
        $unit->save();
        $this->actingAs($admin)->put('/users/'.$staff->id, $this->fields($staff) + ['organization_unit_id' => $unit->id, 'manager_id' => $admin->id])->assertRedirect('/users');
        $staff->refresh();
        $this->assertSame('Departemen lama', $staff->department);
        $this->assertSame($unit->id, (int) $staff->organization_unit_id);
        $this->assertSame($admin->id, (int) $staff->manager_id);
        $event = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->first();
        $this->assertSame($unit->id, json_decode($event->after, true)['organization_unit_id']);
        $this->get('/users/'.$staff->id.'/edit')->assertSee('Departemen lama')->assertSee('Operasional');
        $this->put('/units/'.$unit->id, ['code' => 'OPS', 'name' => 'Operasional', 'is_active' => 0])->assertRedirect();
        $this->assertSame($unit->id, (int) $staff->fresh()->organization_unit_id);
        $this->put('/users/'.$staff->id, $this->fields($staff) + ['organization_unit_id' => $unit->id])->assertRedirect();
        $other = $this->user($company);
        $this->putJson('/users/'.$other->id, $this->fields($other) + ['organization_unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors('organization_unit_id');
        $this->put('/users/'.$staff->id, $this->fields($staff) + ['organization_unit_id' => null, 'manager_id' => null])->assertRedirect();
        $this->assertNull($staff->fresh()->organization_unit_id);
        $this->assertNull($staff->fresh()->manager_id);
    }

    public function test_graph_rejects_self_cycles_foreign_and_new_inactive_links(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $a = $this->user($company);
        $b = $this->user($company);
        $c = $this->user($company);
        $foreign = $this->user($this->company());
        $this->actingAs($admin)->putJson('/users/'.$a->id, $this->fields($a) + ['manager_id' => $a->id])->assertUnprocessable()->assertJsonValidationErrors('manager_id');
        $this->putJson('/users/'.$a->id, $this->fields($a) + ['manager_id' => $foreign->id])->assertUnprocessable();
        $this->putJson('/users/'.$a->id, $this->fields($a) + ['organization_unit_id' => 99999])->assertUnprocessable();
        $this->put('/users/'.$a->id, $this->fields($a) + ['manager_id' => $b->id])->assertRedirect();
        $this->put('/users/'.$b->id, $this->fields($b) + ['manager_id' => $c->id])->assertRedirect();
        $this->putJson('/users/'.$c->id, $this->fields($c) + ['manager_id' => $a->id])->assertUnprocessable()->assertJsonValidationErrors('manager_id');
        $this->assertNull($c->fresh()->manager_id);
        $this->put('/users/'.$b->id, $this->fields($b) + ['employment_status' => 'inactive', 'lifecycle_reason' => 'Nonaktif'])->assertRedirect();
        $this->put('/users/'.$a->id, $this->fields($a) + ['manager_id' => $b->id])->assertRedirect();
        $this->putJson('/users/'.$c->id, $this->fields($c) + ['manager_id' => $b->id])->assertUnprocessable();
        $this->get('/users/'.$a->id.'/edit')->assertViewHas('managers', fn ($rows) => $rows->contains('id', $b->id) && ! $rows->contains('id', $a->id));
    }

    public function test_missing_schema_fails_closed_and_migration_preserves_legacy_data(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $staff->update(['department' => 'Legacy']);
        $migration = require database_path('migrations/2026_10_21_000002_add_employee_organization.php');
        $migration->down();
        $this->actingAs($admin)->getJson('/units/999/edit')->assertStatus(503);
        $this->getJson('/users')->assertStatus(503);
        $migration->up();
        $this->assertSame('Legacy', $staff->fresh()->department);
        $this->assertNull($staff->fresh()->manager_id);
        $this->assertNull($staff->fresh()->organization_unit_id);
        $this->assertDatabaseCount('organization_units', 0);
    }
}
