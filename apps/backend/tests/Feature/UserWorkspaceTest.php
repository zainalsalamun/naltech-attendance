<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWorkspaceTest extends TestCase
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

    public function test_list_search_pagination_and_forms_are_scoped_to_company(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        foreach (range(1, 12) as $number) {
            $this->member($company, sprintf('Karyawan %02d', $number));
        }
        $other = $this->company();
        $outsider = $this->member($other, 'Rahasia perusahaan lain');
        $shift = new Shift(['name' => 'Shift asing', 'time_in' => '08:00', 'time_out' => '17:00']);
        $shift->company_id = $other->id;
        $shift->save();
        $this->actingAs($admin)->get('/users?name=Karyawan&page=2')->assertOk()
            ->assertDontSee('Rahasia perusahaan lain')->assertViewHas('users', fn ($rows) => $rows->total() === 12 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'name=Karyawan'));
        $this->get('/users/create')->assertOk()->assertSee('Tambah karyawan')->assertDontSee('Shift asing');
        $this->get('/users/'.$admin->id.'/edit')->assertOk()->assertSee('Simpan perubahan')->assertDontSee('Shift asing');
        $this->get('/users?name=TidakAda')->assertOk()->assertSee('Tidak ada karyawan');
        $this->getJson('/users?name[]=invalid')->assertUnprocessable();
        $this->get('/users/'.$outsider->id.'/edit')->assertNotFound();
        $this->delete('/users/'.$outsider->id)->assertNotFound();
        $this->actingAs($outsider)->get('/users/create')->assertForbidden();
    }

    public function test_create_and_update_preserve_company_and_blank_password(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $other = $this->company();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Karyawan Baru', 'email' => 'new.employee@example.test', 'password' => 'password123',
            'phone' => '+62 081234', 'company_id' => $other->id, 'employee_number' => 'EMP-NEW',
        ])->assertRedirect(route('users.index'));
        $employee = User::where('email', 'new.employee@example.test')->firstOrFail();
        $this->assertSame($company->id, $employee->company_id);
        $this->assertSame('staff', $employee->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $employee->password));
        $hash = $employee->password;
        $this->put('/users/'.$employee->id, [
            'name' => 'Nama diperbarui', 'email' => $employee->email, 'password' => '', 'role' => 'staff',
            'phone' => '081234567', 'department' => 'Operasional', 'company_id' => $other->id,
            'version' => (int) ($employee->fresh()->version ?? 0),
        ])->assertRedirect(route('users.index'));
        $this->assertSame($hash, $employee->fresh()->password);
        $this->assertSame($company->id, $employee->fresh()->company_id);
        $this->put('/users/'.$employee->id, ['name' => 'Nama', 'email' => $employee->email, 'password' => 'newpassword123', 'version' => (int) ($employee->fresh()->version ?? 0)])
            ->assertRedirect(route('users.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $employee->fresh()->password));
        $this->delete('/users/'.$employee->id, ['lifecycle_reason' => 'Kontrak selesai', 'version' => (int) ($employee->fresh()->version ?? 0)])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'employment_status' => 'inactive']);
        $this->assertNotNull($employee->fresh()->archived_at);
    }

    public function test_invalid_fields_and_foreign_shift_are_rejected_without_changing_employee(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $employee = $this->member($company, 'Staf');
        $other = $this->company();
        $shift = new Shift(['name' => 'Shift asing', 'time_in' => '08:00', 'time_out' => '17:00']);
        $shift->company_id = $other->id;
        $shift->save();
        $this->actingAs($admin)->putJson('/users/'.$employee->id, [
            'name' => ['invalid'], 'email' => $admin->email, 'password' => 'short', 'role' => 'owner',
            'shift_id' => $shift->id, 'phone' => ['invalid'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'shift_id', 'phone']);
        $this->assertSame('Staf', $employee->fresh()->name);
        $this->assertNull($employee->fresh()->shift_id);
        $this->from('/users/create')->post('/users', [
            'name' => 'Isian tetap', 'email' => $admin->email, 'password' => 'password123', 'department' => 'Teknis',
        ])->assertRedirect('/users/create')->assertSessionHasErrors('email')
            ->assertSessionHasInput('name', 'Isian tetap')->assertSessionHasInput('department', 'Teknis');
        $this->get('/users/create')->assertOk()->assertSee('value="Isian tetap"', false)
            ->assertDontSee('value="password123"', false);
    }
}
