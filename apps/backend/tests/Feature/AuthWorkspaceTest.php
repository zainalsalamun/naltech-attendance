<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWorkspaceTest extends TestCase
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

    public function test_login_profile_and_logout_use_real_session_flow(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Selamat datang kembali')->assertDontSee('auth-register.html');
        $this->get('/home')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($admin);
        $this->get('/')->assertRedirect(route('home'));
        $this->get('/features-profile.html')->assertOk()->assertSee($admin->email)->assertSee('Profil akun')
            ->assertDontSee('Save Changes')->assertDontSee('layouts.app');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/features-profile.html')->assertRedirect(route('login'));
    }

    public function test_wrong_password_invalid_input_and_throttling_remain_enforced(): void
    {
        $admin = $this->member($this->company(), 'Admin', 'admin');
        $this->from('/login')->post('/login', ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email')->assertSessionHasInput('email', $admin->email);
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee('Belum dapat masuk.')->assertDontSee('value="wrong-password"', false);
        $this->postJson('/login', ['email' => ['invalid'], 'password' => 'wrong-password'])->assertUnprocessable();
        foreach (range(1, 5) as $i) {
            $this->postJson('/login', ['email' => 'throttle@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'throttle@example.test', 'password' => 'wrong-password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_unassigned_account_and_staff_receive_clear_boundaries(): void
    {
        $company = $this->company();
        $staff = $this->member($company, 'Staf');
        $this->actingAs($staff)->get('/features-profile.html')->assertOk()->assertSee($staff->email)->assertDontSee('href="'.route('users.index').'"', false);
        $this->get('/users')->assertForbidden()->assertSee('Akses belum tersedia');
        $staff->forceFill(['company_id' => null])->save();
        $this->get('/home')->assertForbidden()->assertSee('Hubungi pengelola perusahaan.');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_and_logout_require_csrf_and_unsupported_routes_are_absent(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'example@example.test', 'password' => 'invalid'])->assertStatus(419)->assertSee('Sesi kedaluwarsa');
        $this->post('/logout')->assertStatus(419);
        $this->app['env'] = 'testing';
        $admin = $this->member($this->company(), 'Admin', 'admin');
        $this->actingAs($admin);
        foreach (['/users/'.$admin->id, '/companies', '/companies/create', '/attendances/create', '/qr_absens/1/edit'] as $path) {
            $this->assertContains($this->get($path)->status(), [404, 405]);
        }
    }
}
