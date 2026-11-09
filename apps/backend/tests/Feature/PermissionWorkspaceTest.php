<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionWorkspaceTest extends TestCase
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

    public function test_filters_and_pagination_keep_company_scope_and_render_details(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan');
        $outsider = $this->member($this->company(), 'Rahasia luar');
        foreach (range(1, 12) as $i) {
            Permission::create(['user_id' => $staff->id, 'date_permission' => '2026-10-19', 'reason' => 'Alasan lengkap', 'is_approved' => false]);
        }
        Permission::create(['user_id' => $staff->id, 'date_permission' => '2026-10-18', 'reason' => 'Disetujui', 'is_approved' => true]);
        $foreign = Permission::create(['user_id' => $outsider->id, 'date_permission' => '2026-10-19', 'reason' => 'Rahasia', 'is_approved' => false]);
        $record = Permission::where('user_id', $staff->id)->first();
        $this->actingAs($admin)->get('/permissions?name=Karyawan&date=2026-10-19&status=unapproved&page=2')
            ->assertOk()->assertDontSee('Rahasia luar')->assertViewHas('permissions', function ($rows) {
                parse_str(parse_url($rows->previousPageUrl(), PHP_URL_QUERY), $query);

                return $rows->total() === 12 && $rows->count() === 2 && $query['status'] === 'unapproved' && $query['date'] === '2026-10-19' && $query['name'] === 'Karyawan';
            });
        $this->get('/permissions?status=approved')->assertOk()->assertViewHas('permissions', fn ($rows) => $rows->total() === 1);
        $this->get('/permissions?name=TidakAda')->assertOk()->assertSee('Tidak ada pengajuan izin');
        $this->get('/permissions/'.$record->id)->assertOk()->assertSee('Alasan lengkap')->assertSee('Tidak ada bukti pendukung.');
        $record->update(['image' => 'example-proof.png']);
        $this->get('/permissions/'.$record->id.'/edit')->assertOk()->assertSee('submission-attachments/permission/'.$record->id)->assertDontSee('storage/permissions/')->assertSee('Simpan keputusan');
        $this->getJson('/permissions?status=invalid&date=invalid')->assertUnprocessable();
        $this->get('/permissions/'.$foreign->id.'/edit')->assertNotFound();
        $this->actingAs($staff)->get('/permissions')->assertForbidden();
    }

    public function test_self_approval_is_forbidden_and_final_decision_cannot_be_overwritten(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $other = $this->member($company, 'Reviewer', 'hrd');
        $record = Permission::create(['user_id' => $admin->id, 'date_permission' => '2026-10-19', 'reason' => 'Izin', 'is_approved' => false]);
        $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Dokumen diperiksa'];
        $this->actingAs($admin)->putJson('/permissions/'.$record->id, $data)->assertForbidden();
        $this->actingAs($other)->from('/permissions/'.$record->id.'/edit')->put('/permissions/'.$record->id, $data)->assertRedirect('/permissions/'.$record->id.'/edit');
        $this->assertEquals(1, $record->fresh()->is_approved);
        $this->putJson('/permissions/'.$record->id, $data)->assertConflict();
        $this->putJson('/permissions/'.$record->id, ['status' => 'rejected', 'approval_version' => 1, 'decision_reason' => 'Overwrite'])->assertConflict();
        $this->assertSame('approved', $record->fresh()->status);
        $this->putJson('/permissions/'.$record->id, [])->assertUnprocessable()->assertJsonValidationErrors(['status', 'approval_version', 'decision_reason']);
    }
}
