<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyWorkspaceTest extends TestCase
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

    private function payload(Company $company): array
    {
        return $company->only(['name', 'email', 'address', 'latitude', 'longitude', 'radius_km', 'time_in', 'time_out', 'attendance_type']) + ['overtime_rate' => 25000];
    }

    public function test_company_pages_are_scoped_and_show_location_configuration(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $other = $this->company();
        $this->actingAs($admin)->get('/companies/'.$company->id)->assertOk()->assertSee('Lokasi kerja dan perusahaan')->assertSee('500 meter')->assertSee('Manual + lokasi');
        $this->get('/companies/'.$company->id.'/edit')->assertOk()->assertSee('Radius absensi (km)')->assertSee('Simpan perubahan');
        $this->get('/companies/'.$other->id)->assertNotFound();
        $this->get('/companies/'.$other->id.'/edit')->assertNotFound();
        $this->put('/companies/'.$other->id, $this->payload($other))->assertNotFound();
        $staff = $this->member($company, 'Staf');
        $this->actingAs($staff)->get('/companies/'.$company->id)->assertForbidden();
        $this->put('/companies/'.$company->id, $this->payload($company))->assertForbidden();
    }

    public function test_updates_keep_current_company_and_server_attendance_rules(): void
    {
        $company = $this->company();
        $other = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $data = array_replace($this->payload($company), ['name' => 'Kantor diperbarui', 'latitude' => '-6.21', 'longitude' => '106.81', 'radius_km' => '0.75', 'time_in' => '22:00', 'time_out' => '06:00', 'attendance_type' => 'QR', 'company_id' => $other->id]);
        $this->actingAs($admin)->put('/companies/'.$company->id, $data)->assertRedirect(route('companies.show', $company));
        $this->assertSame('QR', $company->fresh()->attendance_type);
        $this->assertSame('0.75', $company->fresh()->radius_km);
        $this->assertSame('Kantor', $other->fresh()->name);
        $this->get('/companies/'.$company->id)->assertOk()->assertSee('750 meter')->assertSee('QR + lokasi')->assertSee('Rp 25.000');
        $this->get('/home')->assertOk()->assertSee('750 meter')->assertSee('QR + lokasi');
    }

    public function test_invalid_coordinates_radius_times_methods_and_rates_do_not_save(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $this->actingAs($admin);
        $data = $this->payload($company);
        foreach (['latitude' => 91, 'longitude' => 181, 'radius_km' => 0, 'time_in' => '25:00', 'time_out' => 'invalid', 'attendance_type' => 'Face', 'overtime_rate' => -1] as $field => $value) {
            $this->putJson('/companies/'.$company->id, array_replace($data, [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson('/companies/'.$company->id, array_replace($data, ['radius_km' => '1e309']))->assertUnprocessable();
        $this->putJson('/companies/'.$company->id, array_replace($data, ['overtime_rate' => 1.5]))->assertUnprocessable();
        $this->assertSame('None', $company->fresh()->attendance_type);
        $this->assertSame('0.5', $company->fresh()->radius_km);
        $this->from('/companies/'.$company->id.'/edit')->put('/companies/'.$company->id, array_replace($data, ['name' => 'Nama tetap', 'radius_km' => 0]))
            ->assertSessionHasErrors('radius_km')->assertSessionHasInput('name', 'Nama tetap');
        $this->get('/companies/'.$company->id.'/edit')->assertOk()->assertSee('value="Nama tetap"', false);
        $company->update(['attendance_type' => 'Face']);
        $this->get('/companies/'.$company->id)->assertOk()->assertSee('Wajah — belum tersedia');
    }
}
