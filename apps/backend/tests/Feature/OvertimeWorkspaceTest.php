<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Overtime;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeWorkspaceTest extends TestCase
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

    public function test_filters_forms_and_details_remain_in_company_scope(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan');
        $outside = $this->member($this->company(), 'Rahasia luar');
        foreach (range(1, 12) as $i) {
            Overtime::create(['user_id' => $staff->id, 'date' => '2026-10-19', 'duration' => 90, 'description' => 'Catatan lengkap', 'status' => 'pending']);
        }
        $foreign = Overtime::create(['user_id' => $outside->id, 'date' => '2026-10-19', 'duration' => 60, 'status' => 'pending']);
        $record = Overtime::where('user_id', $staff->id)->first();
        $this->actingAs($admin)->get('/overtimes?name=Karyawan&status=pending&date=2026-10-19&page=2')->assertOk()
            ->assertDontSee('Rahasia luar')->assertViewHas('overtimes', function ($rows) {
                parse_str(parse_url($rows->previousPageUrl(), PHP_URL_QUERY), $q);

                return $rows->total() === 12 && $rows->count() === 2 && $q['name'] === 'Karyawan' && $q['status'] === 'pending' && $q['date'] === '2026-10-19';
            });
        $this->get('/overtimes?status=approved')->assertOk()->assertSee('Tidak ada pengajuan lembur');
        $this->get('/overtimes/create')->assertOk()->assertDontSee('Rahasia luar');
        $this->get('/overtimes/'.$record->id)->assertOk()->assertSee('Catatan lengkap');
        $this->get('/overtimes/'.$record->id.'/edit')->assertOk()->assertSee('Simpan perubahan');
        $this->get('/overtimes/'.$foreign->id)->assertNotFound();
        $this->get('/overtimes/'.$foreign->id.'/edit')->assertNotFound();
        $this->delete('/overtimes/'.$foreign->id)->assertNotFound();
        $this->getJson('/overtimes?status=invalid&date=invalid')->assertUnprocessable();
        $this->actingAs($staff)->get('/overtimes')->assertForbidden();
    }

    public function test_create_forces_pending_and_update_cannot_reassign_owner(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Staf');
        $other = $this->member($company, 'Rekan');
        $outside = $this->member($this->company(), 'Luar');
        $this->actingAs($admin)->postJson('/overtimes', ['user_id' => $outside->id, 'date' => '2026-10-19', 'duration' => 60])->assertUnprocessable();
        $this->post('/overtimes', ['user_id' => $staff->id, 'date' => '2026-10-19', 'duration' => 90, 'status' => 'approved'])->assertRedirect(route('overtimes.index'));
        $record = Overtime::firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->put('/overtimes/'.$record->id, ['date' => '2026-10-20', 'duration' => 120, 'description' => 'Selesai', 'status' => 'pending', 'approval_version' => 0, 'user_id' => $other->id])
            ->assertRedirect(route('overtimes.index'));
        $this->assertEquals($staff->id, $record->fresh()->user_id);
        $this->assertSame('pending', $record->fresh()->status);
        $this->post('/submission-decisions/overtime/'.$record->id, ['status' => 'approved', 'approval_version' => 1, 'decision_reason' => 'Durasi telah ditinjau'])->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status);
        $this->get('/overtimes?status=approved')->assertViewHas('overtimes', fn ($rows) => $rows->total() === 1);
        $this->postJson('/submission-decisions/overtime/'.$record->id, ['status' => 'rejected', 'approval_version' => 2, 'decision_reason' => 'Overwrite'])->assertConflict();
        $this->deleteJson('/overtimes/'.$record->id, ['approval_version' => 2, 'decision_reason' => 'Delete'])->assertForbidden();
        $this->assertDatabaseHas('overtimes', ['id' => $record->id, 'status' => 'approved']);
    }

    public function test_invalid_duration_status_and_dates_do_not_save(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $this->actingAs($admin);
        foreach ([0, -1, 1.5, 1441, 2147483648] as $duration) {
            $this->postJson('/overtimes', ['user_id' => $admin->id, 'date' => '2026-10-19', 'duration' => $duration])->assertUnprocessable()->assertJsonValidationErrors('duration');
        }
        $this->post('/overtimes', ['user_id' => $admin->id, 'date' => '2026-10-19', 'duration' => 1440])->assertRedirect(route('overtimes.index'));
        $this->assertDatabaseHas('overtimes', ['user_id' => $admin->id, 'duration' => 1440]);
        Overtime::truncate();
        $this->from('/overtimes/create')->post('/overtimes', ['user_id' => $admin->id, 'date' => 'invalid', 'duration' => 60, 'description' => 'Isian tetap'])
            ->assertSessionHasErrors('date')->assertSessionHasInput('description', 'Isian tetap');
        $this->assertDatabaseCount('overtimes', 0);
        $record = Overtime::create(['user_id' => $admin->id, 'date' => '2026-10-19', 'duration' => 60, 'status' => 'pending']);
        $this->putJson('/overtimes/'.$record->id, ['date' => '2026-10-19', 'duration' => 60, 'status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('pending', $record->fresh()->status);
    }

    public function test_inactive_and_archived_employees_are_rejected_on_store_but_history_remains_readable(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan Aktif');
        $this->actingAs($admin);

        // Active staff can have overtime created
        $this->post('/overtimes', ['user_id' => $staff->id, 'date' => '2026-10-19', 'duration' => 60])->assertRedirect(route('overtimes.index'));
        $record = Overtime::firstOrFail();

        // Deactivate staff: cannot create new overtime, but history remains readable
        $staff->forceFill(['employment_status' => 'inactive', 'deactivated_at' => now()])->save();
        $this->assertFalse($staff->isEmploymentActive());

        // History remains accessible on index and show
        $this->get('/overtimes')->assertOk()->assertSee('Karyawan Aktif');
        $this->get('/overtimes/'.$record->id)->assertOk()->assertSee('Karyawan Aktif');

        // New overtime for deactivated employee must be rejected
        $this->postJson('/overtimes', ['user_id' => $staff->id, 'date' => '2026-10-20', 'duration' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        // Archived employee must also be rejected
        $staff->forceFill(['archived_at' => now()])->save();
        $this->postJson('/overtimes', ['user_id' => $staff->id, 'date' => '2026-10-20', 'duration' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        // create() view dropdown only includes active employees
        $this->get('/overtimes/create')->assertOk()->assertDontSee('value="'.$staff->id.'"', false);
    }

    public function test_api_overtime_index_supports_pagination_and_page_two(): void
    {
        $company = $this->company();
        $staff = $this->member($company, 'Staf API');
        foreach (range(1, 25) as $i) {
            Overtime::create(['user_id' => $staff->id, 'date' => '2026-10-19', 'duration' => 60 + $i, 'status' => 'pending']);
        }

        $this->actingAs($staff);

        // Page 1
        $res1 = $this->getJson('/api/api-overtimes?page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Success')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(20, 'data');

        // Page 2
        $res2 = $this->getJson('/api/api-overtimes?page=2')
            ->assertOk()
            ->assertJsonPath('message', 'Success')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(5, 'data');

        // API store respects technical cap 1440
        $this->postJson('/api/api-overtimes', ['date' => '2026-10-20', 'duration' => 1441])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration');

        $this->postJson('/api/api-overtimes', ['date' => '2026-10-20', 'duration' => 1440])
            ->assertCreated();
    }
}
