<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftWorkspaceTest extends TestCase
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

    private function shift(Company $company, string $name = 'Pagi', string $start = '08:00:00', string $end = '17:00:00'): Shift
    {
        $shift = new Shift(['name' => $name, 'time_in' => $start, 'time_out' => $end]);
        $shift->company_id = $company->id;
        $shift->save();

        return $shift;
    }

    public function test_search_pagination_counts_and_detail_use_active_company(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        foreach (range(1, 12) as $i) {
            $this->shift($company, sprintf('Pagi %02d', $i));
        }
        $record = Shift::where('company_id', $company->id)->first();
        $this->member($company, 'Staf', shift: $record);
        $foreign = $this->shift($this->company(), 'Rahasia luar');
        $this->actingAs($admin)->get('/shifts?name=Pagi&page=2')->assertOk()->assertDontSee('Rahasia luar')
            ->assertViewHas('shifts', fn ($rows) => $rows->total() === 12 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'name=Pagi'));
        $this->get('/shifts/'.$record->id)->assertOk()->assertViewHas('shift', fn ($shift) => $shift->users_count === 1);
        $this->get('/shifts/'.$record->id.'/edit')->assertOk()->assertSee('Simpan perubahan');
        $this->get('/shifts/create')->assertOk()->assertSee('Tambah shift');
        $this->get('/shifts?name=TidakAda')->assertOk()->assertSee('Tidak ada shift');
        $this->getJson('/shifts?name[]=invalid')->assertUnprocessable();
        $this->get('/shifts/'.$foreign->id)->assertNotFound();
        $this->get('/shifts/'.$foreign->id.'/edit')->assertNotFound();
        $this->put('/shifts/'.$foreign->id, ['name' => 'Ganti', 'time_in' => '08:00', 'time_out' => '17:00'])->assertNotFound();
        $this->delete('/shifts/'.$foreign->id)->assertNotFound();
        $staff = $this->member($company, 'Staf lain');
        $this->actingAs($staff)->get('/shifts')->assertForbidden();
    }

    public function test_mutations_validate_times_allow_overnight_and_preserve_company(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $other = $this->company();
        $this->actingAs($admin)->post('/shifts', ['name' => 'Malam', 'time_in' => '22:00', 'time_out' => '06:00', 'company_id' => $other->id])->assertRedirect(route('shifts.index'));
        $record = Shift::firstOrFail();
        $this->assertEquals($company->id, $record->company_id);
        $this->assertSame('22:00:00', $record->time_in);
        $staff = $this->member($company, 'Staf', shift: $record);
        $this->get('/shifts')->assertOk()->assertSee('Lintas hari');
        $this->put('/shifts/'.$record->id, ['name' => 'Pagi', 'time_in' => '08:00:30', 'time_out' => '17:00:30', 'company_id' => $other->id])->assertRedirect();
        $this->assertSame('08:00:30', $record->fresh()->time_in);
        $this->assertEquals($company->id, $record->fresh()->company_id);
        $this->delete('/shifts/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing('shifts', ['id' => $record->id]);
        $this->assertNull($staff->fresh()->shift_id);
    }

    public function test_invalid_times_and_equal_times_do_not_save_and_input_is_restored(): void
    {
        $admin = $this->member($this->company(), 'Admin', 'admin');
        $this->actingAs($admin);
        foreach ([['25:00', '17:00'], ['08:00', 'invalid'], ['08:00', '08:00:00']] as [$start,$end]) {
            $this->postJson('/shifts', ['name' => 'Shift', 'time_in' => $start, 'time_out' => $end])->assertUnprocessable();
        }
        $this->from('/shifts/create')->post('/shifts', ['name' => 'Nama tetap', 'time_in' => '08:00', 'time_out' => ''])
            ->assertSessionHasErrors('time_out')->assertSessionHasInput('name', 'Nama tetap');
        $this->get('/shifts/create')->assertOk()->assertSee('value="Nama tetap"', false);
        $this->assertDatabaseCount('shifts', 0);
    }
}
