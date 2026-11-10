<?php

namespace Tests\MySql;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCorrectionConcurrencyMySqlTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        Connection::configure($app);

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    private function fixture(): array
    {
        $company = Company::create([
            'name' => 'Correction Concurrency QA',
            'email' => 'corr-race@example.test',
            'address' => 'Fixture',
            'latitude' => '0',
            'longitude' => '0',
            'radius_km' => 1,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        $hr1 = User::factory()->create(['role' => 'hrd']);
        $hr1->forceFill(['company_id' => $company->id])->save();

        $hr2 = User::factory()->create(['role' => 'hrd']);
        $hr2->forceFill(['company_id' => $company->id])->save();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->forceFill(['company_id' => $company->id])->save();

        $att = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-21',
            'time_in' => '08:30:00',
            'time_out' => '17:00:00',
            'latlon_in' => '-6.2,106.8',
            'latlon_out' => '-6.2,106.8',
            'checked_in_at' => '2026-10-21 01:30:00',
            'checked_out_at' => '2026-10-21 10:00:00',
            'attendance_timezone' => 'Asia/Jakarta',
            'version' => 0,
        ]);

        return [$company, $hr1, $hr2, $staff, $att];
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
            usleep(250000);

            foreach ($processes as $process) {
                $this->assertTrue(proc_get_status($process)['running'], 'Workers must contend on the held company lock');
            }

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
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
            DB::purge('blocker');
        }
    }

    public function test_concurrent_corrections_with_same_version_accepts_one_and_rejects_other(): void
    {
        [$company, $hr1, $hr2, $staff, $att] = $this->fixture();

        $results = $this->race($company->id, [
            [
                'operation' => 'correct',
                'actor' => $hr1->id,
                'attendance_id' => $att->id,
                'checkin_date' => '2026-10-21',
                'time_in' => '08:00:00',
                'latlon_in' => '-6.2,106.8',
                'reason' => 'Koreksi Admin Satu',
                'version' => 0,
            ],
            [
                'operation' => 'correct',
                'actor' => $hr2->id,
                'attendance_id' => $att->id,
                'checkin_date' => '2026-10-21',
                'time_in' => '07:45:00',
                'latlon_in' => '-6.2,106.8',
                'reason' => 'Koreksi Admin Dua',
                'version' => 0,
            ],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);

        // One worker succeeds (200), the stale worker is rejected with 409 Conflict
        $this->assertSame([200, 409], $statuses);

        // Version incremented exactly once (0 -> 1)
        $att->refresh();
        $this->assertSame(1, (int) $att->version);

        // Exactly one audit event recorded
        $this->assertSame(1, DB::table('attendance_correction_events')->where('attendance_id', $att->id)->count());
    }
}
