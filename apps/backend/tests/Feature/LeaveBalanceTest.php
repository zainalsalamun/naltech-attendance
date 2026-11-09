<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
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
        $company = Company::create([
            'name' => 'Kantor QA',
            'email' => 'qa@example.test',
            'address' => 'Jakarta',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'radius_km' => '0.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]), // Senin - Jumat
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $company;
    }

    private function member(Company $company, string $role = 'staff', ?int $managerId = null): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill([
            'company_id' => $company->id,
            'manager_id' => $managerId,
            'employment_status' => 'active',
        ])->save();

        return $user;
    }

    private function type(Company $company, array $overrides = []): LeaveType
    {
        $type = new LeaveType;
        $type->forceFill(array_merge([
            'company_id' => $company->id,
            'code' => 'ANNUAL',
            'name' => 'Cuti Tahunan',
            'is_active' => true,
            'deducts_balance' => true,
            'annual_quota_days' => 12,
            'requires_attachment' => false,
            'requirements' => 'Pemberitahuan minimal H-3.',
            'version' => 0,
        ], $overrides));
        $type->save();

        return $type;
    }

    public function test_preview_is_shared_scoped_period_aware_and_does_not_reserve_balance(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $owner = $this->member($company);
        $type = $this->type($company);
        app(\App\Services\LeaveBalanceService::class)->allocate($hr, $owner, $type, 2027, 4, 'Preview test allocation');
        $before = \Illuminate\Support\Facades\DB::table('leave_balance_ledgers')->count();
        $data = ['start_date' => '2027-01-04', 'end_date' => '2027-01-05', 'leave_type_id' => $type->id];
        $web = $this->actingAs($owner)->postJson('/leave-calculate', $data)->assertOk()->assertJsonPath('period_year', 2027)->assertJsonPath('balance.remaining_days', 4)->assertJsonPath('balance.remaining_after', 2);
        $api = $this->postJson('/api/leave-calculate', $data)->assertOk()->assertJsonPath('data.balance.period_year', 2027);
        $this->assertSame($web->json('balance'), $api->json('data.balance'));
        $this->assertSame($before, \Illuminate\Support\Facades\DB::table('leave_balance_ledgers')->count());
        $type->forceFill(['is_active' => false])->save();
        foreach (['/leave-calculate', '/api/leave-calculate'] as $url) {
            $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
        }
    }

    public function test_calculate_days_breakdown_and_cross_year_prevention(): void
    {
        $company = $this->company();
        $service = app(LeaveBalanceService::class);

        // 1. Single working day (Wednesday 2026-10-21)
        $res = $service->calculateDays($company, '2026-10-21', '2026-10-21');
        $this->assertSame(1, $res['total_calendar_days']);
        $this->assertSame(1, $res['working_days_count']);
        $this->assertSame(0, $res['skipped_days_count']);
        $this->assertTrue($res['calendar_complete']);

        // 2. Range across weekend (Friday 2026-10-23 to Monday 2026-10-26: 4 cal days, 2 working days, 2 weekend days)
        $resWeekend = $service->calculateDays($company, '2026-10-23', '2026-10-26');
        $this->assertSame(4, $resWeekend['total_calendar_days']);
        $this->assertSame(2, $resWeekend['working_days_count']);
        $this->assertSame(2, $resWeekend['skipped_days_count']);

        // 3. End before start -> validation error
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->calculateDays($company, '2026-10-25', '2026-10-20');
    }

    public function test_cross_year_range_is_rejected_with_split_instruction(): void
    {
        $company = $this->company();
        $service = app(LeaveBalanceService::class);

        try {
            $service->calculateDays($company, '2026-12-30', '2027-01-02');
            $this->fail('Cross-year range must throw ValidationException');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('end_date', $e->errors());
            $this->assertStringContainsString('pergantian tahun kalender', $e->errors()['end_date'][0]);
        }
    }

    public function test_leave_balance_allocation_and_adjustment_preserves_invariant(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $staff = $this->member($company, 'staff');
        $type = $this->type($company);
        $service = app(LeaveBalanceService::class);

        // Initial allocation of 12 days
        $bal = $service->allocate($hr, $staff, $type, 2026, 12, 'Alokasi awal tahun 2026');
        $this->assertSame(12, $bal->allocated_days);
        $this->assertSame(12, $bal->remaining_days);
        $this->assertSame(0, $bal->reserved_days);
        $this->assertSame(0, $bal->used_days);

        // Verify ledger record
        $this->assertDatabaseHas('leave_balance_ledgers', [
            'leave_balance_id' => $bal->id,
            'transaction_type' => 'allocation',
            'amount_days' => 12,
            'previous_allocated' => 0,
            'current_allocated' => 12,
            'current_remaining' => 12,
        ]);

        // Adjustment: increase to 15 days
        $bal = $service->allocate($hr, $staff, $type, 2026, 15, 'Penyesuaian kuota prestasi');
        $this->assertSame(15, $bal->allocated_days);
        $this->assertSame(15, $bal->remaining_days);

        // Verify ledger record for adjustment
        $this->assertDatabaseHas('leave_balance_ledgers', [
            'leave_balance_id' => $bal->id,
            'transaction_type' => 'adjustment',
            'amount_days' => 3,
            'previous_allocated' => 12,
            'current_allocated' => 15,
        ]);
    }

    public function test_full_leave_lifecycle_reserve_use_and_ledger_audit(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $manager = $this->member($company, 'supervisor');
        $staff = $this->member($company, 'staff', $manager->id);
        $type = $this->type($company);

        // 1. Allocate 10 days
        app(LeaveBalanceService::class)->allocate($hr, $staff, $type, 2026, 10, 'Alokasi kuota');

        // 2. Staff requests leave: Wednesday to Friday (3 working days)
        $response = $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'leave_type_id' => $type->id,
            'reason' => 'Liburan keluarga',
        ])->assertCreated();

        $permId = $response->json('data.id');
        $bal = LeaveBalance::where('user_id', $staff->id)->where('period_year', 2026)->first();

        // Remaining decreased by 3, reserved increased by 3, used = 0
        $this->assertSame(10, $bal->allocated_days);
        $this->assertSame(7, $bal->remaining_days);
        $this->assertSame(3, $bal->reserved_days);
        $this->assertSame(0, $bal->used_days);

        // 3. Manager approves stage 1 (if 2-stage approval is active) or direct admin approval
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$permId, [
            'status' => 'approved',
            'approval_version' => 0,
            'decision_reason' => 'Disetujui HR',
        ])->assertOk();

        // After final approval: reserved becomes used
        $bal->refresh();
        $this->assertSame(10, $bal->allocated_days);
        $this->assertSame(7, $bal->remaining_days);
        $this->assertSame(0, $bal->reserved_days);
        $this->assertSame(3, $bal->used_days);

        // Verify ledger has 'use' transaction
        $this->assertDatabaseHas('leave_balance_ledgers', [
            'leave_balance_id' => $bal->id,
            'permission_id' => $permId,
            'transaction_type' => 'use',
            'amount_days' => 3,
            'current_remaining' => 7,
            'current_used' => 3,
        ]);
    }

    public function test_approved_leave_cancellation_flow_and_balance_restoration(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $staff = $this->member($company, 'staff');
        $type = $this->type($company);

        // 1. Allocate 10 days
        app(LeaveBalanceService::class)->allocate($hr, $staff, $type, 2026, 10, 'Alokasi kuota');

        // 2. Staff submits 2 days (2026-10-21 to 2026-10-22)
        $response = $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-22',
            'leave_type_id' => $type->id,
            'reason' => 'Perlu urusan',
        ])->assertCreated();
        $permId = $response->json('data.id');

        // 3. HR approves
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$permId, [
            'status' => 'approved',
            'approval_version' => 0,
            'decision_reason' => 'Disetujui HR',
        ])->assertOk();

        $bal = LeaveBalance::where('user_id', $staff->id)->where('period_year', 2026)->first();
        $this->assertSame(2, $bal->used_days);
        $this->assertSame(8, $bal->remaining_days);

        // 4. Staff requests cancellation of approved leave
        $this->actingAs($staff)->postJson('/submission-cancellations/permission/'.$permId, [
            'approval_version' => 1,
            'decision_reason' => 'Acara keluarga dibatalkan',
            'request_cancellation' => true,
        ])->assertOk();

        $perm = Permission::findOrFail($permId);
        $this->assertSame('cancellation_requested', $perm->status);

        // 5. HR approves cancellation -> status becomes cancelled, used restored to remaining
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$permId, [
            'status' => 'approved',
            'approval_version' => 2,
            'decision_reason' => 'Pembatalan disetujui, saldo dikembalikan',
        ])->assertOk();

        $perm->refresh();
        $bal->refresh();
        $this->assertSame('cancelled', $perm->status);
        $this->assertSame(0, $bal->used_days);
        $this->assertSame(10, $bal->remaining_days);

        // Verify ledger has restore_used transaction
        $this->assertDatabaseHas('leave_balance_ledgers', [
            'leave_balance_id' => $bal->id,
            'permission_id' => $permId,
            'transaction_type' => 'restore_used',
            'amount_days' => 2,
            'current_remaining' => 10,
            'current_used' => 0,
        ]);
    }

    public function test_overlap_validation_rejects_conflicting_date_ranges(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $staff = $this->member($company, 'staff');
        $type = $this->type($company);

        app(LeaveBalanceService::class)->allocate($hr, $staff, $type, 2026, 15, 'Alokasi kuota');

        // First leave: 2026-10-20 to 2026-10-23
        $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-23',
            'leave_type_id' => $type->id,
            'reason' => 'Izin pertama',
        ])->assertCreated();

        // Second leave overlapping (2026-10-22 to 2026-10-24) -> rejected
        $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-22',
            'end_date' => '2026-10-24',
            'leave_type_id' => $type->id,
            'reason' => 'Izin kedua bertumpuk',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');

        // Third leave non-overlapping (2026-10-27 to 2026-10-28) -> allowed
        $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-27',
            'end_date' => '2026-10-28',
            'leave_type_id' => $type->id,
            'reason' => 'Izin ketiga terpisah',
        ])->assertCreated();
    }

    public function test_unallocated_or_insufficient_balance_is_rejected(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $staff = $this->member($company, 'staff');
        $type = $this->type($company);

        // 1. Without allocation, submitting leave is rejected with clear instruction
        $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-21',
            'leave_type_id' => $type->id,
            'reason' => 'Tanpa alokasi',
        ])->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');

        // 2. Allocate 2 days
        app(LeaveBalanceService::class)->allocate($hr, $staff, $type, 2026, 2, 'Alokasi 2 hari');

        // Submitting 3 days (Wednesday - Friday: 3 working days) -> rejected for insufficient balance
        $this->actingAs($staff)->postJson('/api/api-permissions', [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'leave_type_id' => $type->id,
            'reason' => 'Kurang saldo',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_web_leave_calculate_endpoint_returns_accurate_preview(): void
    {
        $company = $this->company();
        $hr = $this->member($company, 'hrd');
        $staff = $this->member($company, 'staff');
        $type = $this->type($company);

        app(LeaveBalanceService::class)->allocate($hr, $staff, $type, 2026, 10, 'Alokasi 10 hari');

        $this->actingAs($staff)->postJson('/leave-calculate', [
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-23',
            'leave_type_id' => $type->id,
        ])->assertOk()
            ->assertJsonPath('calculation.working_days_count', 3)
            ->assertJsonPath('calculation.total_calendar_days', 3)
            ->assertJsonPath('balance.remaining_days', 10)
            ->assertJsonPath('balance.remaining_after', 7)
            ->assertJsonPath('balance.sufficient', true);
    }
}
