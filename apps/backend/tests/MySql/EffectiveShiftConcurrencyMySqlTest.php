<?php

namespace Tests\MySql;

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EffectiveShiftConcurrencyMySqlTest extends TestCase
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
            'name' => 'Effective Shift Concurrency QA',
            'email' => 'shift-race@example.test',
            'address' => 'Fixture',
            'latitude' => '0',
            'longitude' => '0',
            'radius_km' => 1,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        $hr1 = User::factory()->create(['name' => 'HR One', 'role' => 'hrd']);
        $hr1->forceFill(['company_id' => $company->id])->save();

        $hr2 = User::factory()->create(['name' => 'HR Two', 'role' => 'hrd']);
        $hr2->forceFill(['company_id' => $company->id])->save();

        $staff = User::factory()->create(['name' => 'Staff Target', 'role' => 'staff']);
        $staff->forceFill(['company_id' => $company->id])->save();

        $shiftMorning = new Shift(['name' => 'Pagi QA', 'time_in' => '08:00:00', 'time_out' => '17:00:00']);
        $shiftMorning->company_id = $company->id;
        $shiftMorning->save();

        $shiftNight = new Shift(['name' => 'Malam QA', 'time_in' => '22:00:00', 'time_out' => '06:00:00']);
        $shiftNight->company_id = $company->id;
        $shiftNight->save();

        return [$company, $hr1, $hr2, $staff, $shiftMorning, $shiftNight];
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

    public function test_concurrent_shift_assignments_with_same_expected_id_results_in_one_winner_and_one_conflict(): void
    {
        [$company, $hr1, $hr2, $staff, $shiftMorning, $shiftNight] = $this->fixture();

        $today = now('Asia/Jakarta')->toDateString();

        // Dua HR mencoba menugaskan shift berbeda untuk staf yang sama dengan expected_id = 0
        $results = $this->race($company->id, [
            [
                'operation' => 'assign_effective_shift',
                'actor' => $hr1->id,
                'user_id' => $staff->id,
                'shift_id' => $shiftMorning->id,
                'effective_from' => $today,
                'expected_id' => 0,
                'reason' => 'Penugasan HR 1',
            ],
            [
                'operation' => 'assign_effective_shift',
                'actor' => $hr2->id,
                'user_id' => $staff->id,
                'shift_id' => $shiftNight->id,
                'effective_from' => $today,
                'expected_id' => 0,
                'reason' => 'Penugasan HR 2',
            ],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);

        // Tepat 1 proses berhasil (200) dan 1 proses ditolak (409 Conflict)
        $this->assertSame([200, 409], $statuses);

        // Hanya ada tepat 1 baris penugasan yang tercatat di database
        $assignments = DB::table('employee_shift_assignments')
            ->where('company_id', $company->id)
            ->where('user_id', $staff->id)
            ->get();

        $this->assertCount(1, $assignments);
        $this->assertContains($assignments->first()->shift_id, [$shiftMorning->id, $shiftNight->id]);
    }

    public function test_subsequent_stale_expected_id_rejected_atomically(): void
    {
        [$company, $hr1, $hr2, $staff, $shiftMorning, $shiftNight] = $this->fixture();

        $today = now('Asia/Jakarta')->toDateString();

        // HR 1 berhasil menugaskan shift pertama (expected_id = 0)
        app(\App\Services\EffectiveShift::class)->assign($hr1, [
            'user_id' => $staff->id,
            'shift_id' => $shiftMorning->id,
            'effective_from' => $today,
            'expected_id' => 0,
            'reason' => 'Penugasan pertama berhasil',
        ]);

        $countBefore = DB::table('employee_shift_assignments')->where('user_id', $staff->id)->count();
        $this->assertSame(1, $countBefore);

        // HR 2 mencoba menugaskan menggunakan expected_id lama (0)
        try {
            app(\App\Services\EffectiveShift::class)->assign($hr2, [
                'user_id' => $staff->id,
                'shift_id' => $shiftNight->id,
                'effective_from' => $today,
                'expected_id' => 0, // stale
                'reason' => 'Penugasan kedua dengan stale ID',
            ]);
            $this->fail('Stale expected_id harus ditolak dengan 409 Conflict.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('konflik versi', $e->getMessage());
        }

        // Tidak ada penambahan baris (0 mutasi baru)
        $countAfter = DB::table('employee_shift_assignments')->where('user_id', $staff->id)->count();
        $this->assertSame(1, $countAfter);
    }
}
