<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionEvent;
use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Models\LeaveType;
use App\Models\OrganizationUnit;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederPackagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['initial_setup.admin_password' => bin2hex(random_bytes(16))]);
    }

    public function test_setup_requires_configured_password_and_preserves_existing_password(): void
    {
        foreach ([null, 'short'] as $password) {
            config(['initial_setup.admin_password' => $password]);
            try {
                $this->seed(InitialSetupSeeder::class);
                $this->fail('Missing or weak setup password must fail before writes');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('SETUP_ADMIN_PASSWORD', $exception->getMessage());
            }
            $this->assertDatabaseCount('companies', 0);
            $this->assertDatabaseCount('users', 0);
        }
        $password = bin2hex(random_bytes(16));
        config(['initial_setup.admin_password' => $password]);
        $this->seed(InitialSetupSeeder::class);
        $admin = User::withoutGlobalScopes()->where('role', 'super admin')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $admin->password));
        $hash = $admin->password;
        config(['initial_setup.admin_password' => bin2hex(random_bytes(16))]);
        $this->seed(InitialSetupSeeder::class);
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_initial_setup_seeder_creates_clean_production_foundation_without_transactions(): void
    {
        $this->seed(InitialSetupSeeder::class);

        // 1. Company
        $company = Company::where('email', 'admin@naltech.id')->first();
        $this->assertNotNull($company);
        $this->assertSame('PT Naltech Inovasi Digital', $company->name);
        $this->assertSame('Asia/Jakarta', $company->timezone);

        // 2. Super Admin
        $admin = User::where('email', 'superadmin@naltech.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame('super admin', $admin->role);
        $this->assertSame('ADM-001', $admin->employee_number);
        $this->assertSame('active', $admin->employment_status);
        $this->assertSame($company->id, $admin->company_id);

        // 3. Organization Units
        $this->assertSame(5, OrganizationUnit::where('company_id', $company->id)->count());
        $this->assertTrue(OrganizationUnit::where('company_id', $company->id)->where('code', 'TECH')->exists());

        // 4. Shifts
        $this->assertSame(4, Shift::where('company_id', $company->id)->count());

        // 5. Work Calendar Rules
        $rule = DB::table('work_calendar_rules')->where('company_id', $company->id)->first();
        $this->assertNotNull($rule);
        $this->assertSame('2026-01-01', $rule->effective_from);

        // 6. Leave Types
        $this->assertSame(5, LeaveType::where('company_id', $company->id)->count());
        $ct = LeaveType::where('company_id', $company->id)->where('code', 'CT')->first();
        $this->assertNotNull($ct);
        $this->assertTrue($ct->deducts_balance);
        $this->assertSame(12, $ct->annual_quota_days);

        // 7. Workflows
        $this->assertSame(3, DB::table('approval_workflows')->where('company_id', $company->id)->count());

        // 8. Clean state: zero transactions
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, Permission::count());
        $this->assertSame(0, Overtime::count());
        $this->assertSame(0, Reimbursement::count());
        $this->assertSame(0, LeaveBalance::count());
    }

    public function test_demo_data_seeder_populates_hierarchy_transactions_and_audit_events(): void
    {
        $this->seed(DemoDataSeeder::class);

        $company = Company::where('email', 'admin@naltech.id')->firstOrFail();

        // 1. Hierarchical demo users
        $direktur = User::where('email', 'direktur@naltech.test')->firstOrFail();
        $hr = User::where('email', 'hr@naltech.test')->firstOrFail();
        $supervisor = User::where('email', 'supervisor@naltech.test')->firstOrFail();
        $staff = User::where('email', 'staff@naltech.test')->firstOrFail();

        $this->assertSame($direktur->id, $hr->manager_id);
        $this->assertSame($hr->id, $supervisor->manager_id);
        $this->assertSame($supervisor->id, $staff->manager_id);

        // 2. Balances and Ledgers
        $annualLeave = LeaveType::where('company_id', $company->id)->where('code', 'CT')->firstOrFail();
        $staffBalance = LeaveBalance::where('user_id', $staff->id)->where('leave_type_id', $annualLeave->id)->firstOrFail();
        $this->assertSame(12, $staffBalance->allocated_days);
        $this->assertSame(12, $staffBalance->remaining_days);
        $this->assertSame(1, LeaveBalanceLedger::where('leave_balance_id', $staffBalance->id)->count());

        // 3. Attendances and Audit Trail
        $this->assertGreaterThan(0, Attendance::count());
        $correction = AttendanceCorrectionEvent::first();
        $this->assertNotNull($correction);
        $this->assertSame($hr->id, $correction->actor_id);
        $this->assertNotEmpty($correction->reason);
        $this->assertIsArray($correction->snapshot);

        // 4. Permission, Overtime, Reimbursement demo records
        $this->assertGreaterThan(0, Permission::count());
        $this->assertGreaterThan(0, Overtime::count());
        $this->assertGreaterThan(0, Reimbursement::count());
    }
}
