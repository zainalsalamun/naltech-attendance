<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PensionReviewWorkspaceTest extends TestCase
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

    public function test_search_date_pagination_export_and_read_only_routes(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan');
        $outsider = $this->member($this->company(), 'Rahasia luar');
        foreach (range(1, 12) as $day) {
            Attendance::create(['user_id' => $staff->id, 'date' => sprintf('2026-10-%02d', $day), 'time_in' => '08:00:00', 'latlon_in' => '-6.2,106.8']);
        }
        Attendance::create(['user_id' => $outsider->id, 'date' => '2026-10-01', 'time_in' => '08:00:00', 'latlon_in' => '0,0']);
        $this->actingAs($admin)->get('/verifikasi?name=Karyawan&page=2')->assertOk()->assertDontSee('Rahasia luar')
            ->assertSee('Keputusan verifikasi pensiun belum tersedia.')
            ->assertViewHas('attendances', fn ($rows) => $rows->total() === 12 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'name=Karyawan'));
        $this->get('/verifikasi?name=Karyawan&date=2026-10-01')->assertOk()->assertViewHas('attendances', fn ($rows) => $rows->total() === 1);
        $csv = $this->get('/verifikasi/export-csv?name=Karyawan&date=2026-10-01')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-01', $csv);
        $this->assertStringNotContainsString('2026-10-02', $csv);
        $this->assertStringNotContainsString('Rahasia luar', $csv);
        $this->get('/verifikasi?name=TidakAda')->assertOk()->assertSee('Tidak ada catatan');
        $this->getJson('/verifikasi?date=invalid')->assertUnprocessable();
        $this->getJson('/verifikasi/export-csv?name[]=invalid')->assertUnprocessable();
        $this->get('/verifikasi/1/edit')->assertNotFound();
        $this->delete('/verifikasi/1')->assertNotFound();
        $this->assertDatabaseCount('attendances', 13);
        $this->actingAs($staff)->get('/verifikasi')->assertForbidden();
        $this->get('/verifikasi/export-csv')->assertForbidden();
    }

    public function test_csv_streams_multiple_batches_and_escapes_spreadsheet_formulas(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, '=1+1');
        $records = [];
        foreach (range(0, 500) as $day) {
            $records[] = ['user_id' => $staff->id, 'date' => \Carbon\CarbonImmutable::parse('2026-01-01')->addDays($day)->toDateString(), 'time_in' => '08:00:00', 'latlon_in' => '-6.2,106.8'];
        }
        \Illuminate\Support\Facades\DB::table('attendances')->insert($records);
        $csv = $this->actingAs($admin)->get('/verifikasi/export-csv')->assertOk()->streamedContent();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        $this->assertCount(502, $rows);
        $this->assertSame("'=1+1", $rows[1][1]);
        $this->assertSame("'-6.2,106.8", $rows[1][5]);
        $this->assertSame('2026-01-01', $rows[501][2]);
    }
}
