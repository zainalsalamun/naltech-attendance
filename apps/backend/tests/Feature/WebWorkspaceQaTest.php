<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\QrAbsen;
use App\Models\Reimbursement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebWorkspaceQaTest extends TestCase
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

    public function test_all_supported_web_screens_render_workspace_and_sidebar_links_resolve(): void
    {
        $company = $this->company();
        $admin = $this->member($company, str_repeat('Admin ', 35), 'admin');
        $shift = new Shift(['name' => str_repeat('Shift ', 35), 'time_in' => '08:00:00', 'time_out' => '17:00:00']);
        $shift->company_id = $company->id;
        $shift->save();
        $admin->update(['shift_id' => $shift->id]);
        $attendance = Attendance::create(['user_id' => $admin->id, 'date' => '2026-10-19', 'time_in' => '08:00:00', 'latlon_in' => '-6.2,106.8']);
        $permission = Permission::create(['user_id' => $admin->id, 'date_permission' => '2026-10-19', 'reason' => str_repeat('Alasan panjang ', 50), 'is_approved' => false]);
        $overtime = Overtime::create(['user_id' => $admin->id, 'date' => '2026-10-19', 'duration' => 90, 'description' => 'Catatan', 'status' => 'pending']);
        $reimbursement = Reimbursement::create(['user_id' => $admin->id, 'date' => '2026-10-19', 'amount' => '100000.50', 'description' => 'Biaya', 'status' => 'pending']);
        $qr = new QrAbsen(['date' => '2026-10-19', 'qr_checkin' => 'IN-REVIEW', 'qr_checkout' => 'OUT-REVIEW']);
        $qr->company_id = $company->id;
        $qr->save();
        $paths = ['/home', '/attendances', '/attendance-corrections', '/attendance-recap', '/users', '/permissions', '/overtimes', '/reimbursements', '/shifts', '/companies/'.$company->id, '/qr_absens', '/verifikasi', '/features-profile.html',
            '/users/create', '/users/'.$admin->id.'/edit', '/attendances/'.$attendance->id.'/edit',
            '/permissions/'.$permission->id, '/permissions/'.$permission->id.'/edit', '/overtimes/create', '/overtimes/'.$overtime->id, '/overtimes/'.$overtime->id.'/edit',
            '/reimbursements/create', '/reimbursements/'.$reimbursement->id, '/reimbursements/'.$reimbursement->id.'/edit', '/shifts/create', '/shifts/'.$shift->id, '/shifts/'.$shift->id.'/edit',
            '/companies/'.$company->id.'/edit', '/qr_absens/create', '/qr_absens/'.$qr->id];
        $this->actingAs($admin);
        foreach ($paths as $path) {
            $this->get($path)->assertOk()->assertSee('css/workspace.css', false)->assertSee('aria-label="Tema aplikasi"', false)
                ->assertDontSee('css/modern-custom.css', false)->assertDontSee('gtag(', false);
        }
        $this->assertNull(app(\App\Support\CompanyContext::class)->id);
    }

    public function test_empty_modules_and_manager_roles_render_without_fabricated_records(): void
    {
        $company = $this->company();
        foreach (['admin', 'super admin', 'hrd', 'supervisor'] as $role) {
            $viewer = $this->member($company, 'Pengelola', $role);
            $this->actingAs($viewer)->get('/home')->assertOk();
            foreach (['/attendances', '/attendance-corrections', '/permissions', '/overtimes', '/reimbursements', '/shifts', '/qr_absens', '/verifikasi'] as $path) {
                $this->get($path)->assertOk()->assertSee('empty-state', false);
            }
            $this->get('/attendance-recap?name=NoneMatching')->assertOk()->assertSee('empty-state', false);
            $this->get('/attendance-recap')->assertOk()->assertSee('Tabel Rekapitulasi Periode', false);
        }
    }

    public function test_sidebar_restructuring_six_main_menus_groups_accessibility_and_role_scoping(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Administrator', 'admin');
        $staff = $this->member($company, 'Staff Member', 'staff');

        // Admin view on /home
        $response = $this->actingAs($admin)->get('/home')->assertOk();
        $response->assertSee('sidebar-toggle-btn')
            ->assertSee('data-storage-prefix="naltech.sidebar.groups.'.$admin->id.'.'.$company->id.'"', false)
            ->assertSee('Ringkasan')
            ->assertSee('group-karyawan')
            ->assertSee('group-kehadiran')
            ->assertSee('group-jadwal')
            ->assertSee('group-pengajuan')
            ->assertSee('group-pengaturan')
            ->assertSee('Profil akun')
            ->assertSee('Keluar');

        // Admin visiting /permissions: Pengajuan group must have active child
        $responsePerm = $this->actingAs($admin)->get('/permissions')->assertOk();
        $responsePerm->assertSee('id="btn-group-pengajuan"', false)
            ->assertSee('data-has-active-child="true"', false)
            ->assertSee('aria-current="page"', false);

        // Staff user does not see manager groups (Karyawan, Jadwal, Pengaturan)
        $responseStaff = $this->actingAs($staff)->get('/home')->assertOk();
        $responseStaff->assertSee('Ringkasan')
            ->assertDontSee('group-karyawan')
            ->assertDontSee('group-jadwal')
            ->assertDontSee('group-pengaturan');
    }
}
