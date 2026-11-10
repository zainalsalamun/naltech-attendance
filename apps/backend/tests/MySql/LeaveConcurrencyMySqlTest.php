<?php

namespace Tests\MySql;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeavePolicy;
use App\Services\SubmissionApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveConcurrencyMySqlTest extends TestCase
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
        $company = Company::create(['name' => 'Concurrency QA', 'email' => 'race@example.test', 'address' => 'Fixture', 'latitude' => '0', 'longitude' => '0', 'radius_km' => 1, 'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);
        $company->forceFill(['timezone' => 'Asia/Jakarta'])->save();
        $hr = User::factory()->create(['role' => 'hrd']);
        $hr->forceFill(['company_id' => $company->id])->save();
        $owner = User::factory()->create(['role' => 'staff']);
        $owner->forceFill(['company_id' => $company->id])->save();
        DB::table('work_calendar_rules')->insert(['company_id' => $company->id, 'effective_from' => '2026-01-01', 'weekdays' => json_encode([1, 2, 3, 4, 5]), 'created_at' => now(), 'updated_at' => now()]);
        $type = (new LeaveType)->forceFill(['code' => 'RACE', 'name' => 'QA leave', 'is_active' => true, 'deducts_balance' => true, 'annual_quota_days' => 1, 'requires_attachment' => false]);
        $type->company_id = $company->id;
        $type->save();
        $balance = app(LeaveBalanceService::class)->allocate($hr, $owner, $type, 2026, 1, 'Synthetic QA allocation');

        return [$company, $hr, $owner, $type, $balance];
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

    public function test_simultaneous_submissions_cannot_spend_the_last_day_twice(): void
    {
        [$company,$hr,$owner,$type,$balance] = $this->fixture();
        $results = $this->race($company->id, [
            ['operation' => 'submit', 'actor' => $owner->id, 'type' => $type->id, 'date' => '2026-10-21'],
            ['operation' => 'submit', 'actor' => $owner->id, 'type' => $type->id, 'date' => '2026-10-22'],
        ]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $balance->refresh();
        $this->assertSame(0, $balance->remaining_days);
        $this->assertSame(1, $balance->reserved_days);
        $this->assertSame(0, $balance->used_days);
        $this->assertDatabaseCount('permissions', 1);
        $this->assertSame(1, DB::table('leave_balance_ledgers')->where('transaction_type', 'reserve')->count());
    }

    public function test_concurrent_approval_and_cancellation_restore_each_post_only_once(): void
    {
        [$company,$hr,$owner,$type,$balance] = $this->fixture();
        $row = app(LeavePolicy::class)->submit($owner, ['leave_type_id' => $type->id, 'start_date' => '2026-10-21', 'end_date' => '2026-10-21', 'reason' => 'Synthetic concurrent lifecycle'], null);
        $job = ['operation' => 'approve', 'actor' => $hr->id, 'permission' => $row->id, 'version' => 0];
        $results = $this->race($company->id, [$job, $job]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(1, DB::table('leave_balance_ledgers')->where('transaction_type', 'use')->count());
        $this->assertSame('approved', $row->fresh()->status);
        app(SubmissionApproval::class)->decide($owner, 'permission', $row->id, 'cancelled', 1, 'Request cancellation QA', true);
        $job['version'] = 2;
        $results = $this->race($company->id, [$job, $job]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(1, DB::table('leave_balance_ledgers')->where('transaction_type', 'restore_used')->count());
        $this->assertSame('cancelled', $row->fresh()->status);
        $balance->refresh();
        $this->assertSame(1, $balance->remaining_days);
        $this->assertSame(0, $balance->reserved_days);
        $this->assertSame(0, $balance->used_days);
        $this->assertSame($balance->allocated_days, $balance->remaining_days + $balance->reserved_days + $balance->used_days);
    }
}
