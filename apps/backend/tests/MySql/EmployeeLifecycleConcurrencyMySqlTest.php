<?php

namespace Tests\MySql;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeLifecycleConcurrencyMySqlTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        Connection::configure($app);

        return $app;
    }

    protected function beforeRefreshingDatabase()
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    private function fixture(): array
    {
        $company = Company::create([
            'name' => 'Lifecycle Concurrency QA',
            'email' => 'lifecycle-race@example.test',
            'address' => 'Fixture',
            'latitude' => '0',
            'longitude' => '0',
            'radius_km' => 1,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        $admin1 = User::factory()->create(['name' => 'Admin Alpha', 'role' => 'admin']);
        $admin1->forceFill(['company_id' => $company->id, 'version' => 0])->save();

        $admin2 = User::factory()->create(['name' => 'Admin Beta', 'role' => 'admin']);
        $admin2->forceFill(['company_id' => $company->id, 'version' => 0])->save();

        $staff = User::factory()->create(['name' => 'Staff Charlie', 'role' => 'staff']);
        $staff->forceFill(['company_id' => $company->id, 'version' => 0])->save();

        return [$company, $admin1, $admin2, $staff];
    }

    private function race(int $companyId, array $jobs): array
    {
        $dir = getenv('NALTECH_MYSQL_QA_DIR').'/jobs';
        if (! is_dir($dir)) {
            mkdir($dir, 0700);
        }
        $batch = bin2hex(random_bytes(6));
        $gate = $dir.'/'.$batch.'.go';
        $processes = [];
        $files = [];

        config(['database.connections.blocker' => config('database.connections.mysql')]);
        $blocker = DB::connection('blocker');
        $blocker->beginTransaction();
        $blocker->table('companies')->where('id', $companyId)->lockForUpdate()->first();

        try {
            foreach ($jobs as $i => $job) {
                $file = $dir.'/'.$batch.'-'.$i.'.json';
                file_put_contents($file, json_encode($job + ['gate' => $gate]));
                $files[] = $file;
                $processes[] = proc_open([PHP_BINARY, base_path('../../tools/mysql-qa/worker.php'), $file], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, base_path());
            }

            $deadline = microtime(true) + 20;
            foreach ($files as $file) {
                while (! is_file($file.'.ready') && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($file.'.ready');
            }

            touch($gate);
            $blocker->commit();

            $results = [];
            foreach ($files as $file) {
                $deadline = microtime(true) + 20;
                while (! is_file($file.'.result') && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($file.'.result');
                $results[] = json_decode(file_get_contents($file.'.result'), true);
            }

            return $results;
        } finally {
            if ($blocker->transactionLevel()) {
                $blocker->rollBack();
            }
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    $pStatus = proc_get_status($process);
                    if ($pStatus['running'] ?? false) {
                        proc_terminate($process);
                    }
                    proc_close($process);
                }
            }
            DB::purge('blocker');
        }
    }

    public function test_concurrent_admin_demotion_preserves_at_least_one_admin(): void
    {
        [$company, $admin1, $admin2, $staff] = $this->fixture();

        // Dua admin bersaing mendemosi satu sama lain secara simultan
        $results = $this->race($company->id, [
            [
                'operation' => 'demote_admin',
                'actor' => $admin1->id,
                'target' => $admin2->id,
                'version' => 0,
            ],
            [
                'operation' => 'demote_admin',
                'actor' => $admin2->id,
                'target' => $admin1->id,
                'version' => 0,
            ],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);

        // Tepat 1 berhasil (200) dan 1 ditolak (403)
        $this->assertSame([200, 403], $statuses);

        // Verifikasi database invariant: tepat 1 admin aktif tersisa
        $activeAdmins = User::where('company_id', $company->id)
            ->whereIn('role', ['admin', 'super admin'])
            ->where('employment_status', 'active')
            ->whereNull('archived_at')
            ->count();
        $this->assertSame(1, $activeAdmins);
    }

    public function test_concurrent_employee_update_with_same_version_accepts_one_and_rejects_other_with_409(): void
    {
        [$company, $admin1, $admin2, $staff] = $this->fixture();

        $this->assertSame(0, (int) $staff->version);

        // Dua worker mencoba memperbarui karyawan yang sama dengan version 0 secara bersamaan
        $results = $this->race($company->id, [
            [
                'operation' => 'update_employee',
                'actor' => $admin1->id,
                'target' => $staff->id,
                'name' => 'Charlie By Admin1',
                'employee_number' => 'EMP-V1',
                'version' => 0,
            ],
            [
                'operation' => 'update_employee',
                'actor' => $admin2->id,
                'target' => $staff->id,
                'name' => 'Charlie By Admin2',
                'employee_number' => 'EMP-V2',
                'version' => 0,
            ],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);

        // Tepat 1 berhasil (200) dan 1 ditolak 409 Conflict
        $this->assertSame([200, 409], $statuses);

        // Target record naik tepat 1 versi
        $staff->refresh();
        $this->assertSame(1, (int) $staff->version);

        // Tepat 1 event audit tercatat
        $eventsCount = DB::table('employee_lifecycle_events')->where('user_id', $staff->id)->count();
        $this->assertSame(1, $eventsCount);
    }
}
