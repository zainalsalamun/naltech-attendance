<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use App\Services\LeaveBalanceService;
use App\Services\SubmissionApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalFlowRefinementTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));

        return $app;
    }

    private function company(string $name = 'Kantor'): Company
    {
        $company = Company::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'address' => 'Fixture Address',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'radius_km' => '.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $company;
    }

    private function user(Company $company, string $role = 'staff', string $name = 'Karyawan'): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'name' => $name,
            'password' => 'Password123!',
        ]);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function setupLeaveTypeWithBalance(Company $company, User $user, int $quota = 12): LeaveType
    {
        $leaveType = new LeaveType();
        $leaveType->forceFill([
            'company_id' => $company->id,
            'code' => 'CT-'.uniqid(),
            'name' => 'Cuti Tahunan',
            'annual_quota_days' => $quota,
            'requires_attachment' => false,
            'requirements' => null,
            'deducts_balance' => true,
            'is_active' => true,
            'version' => 1,
        ]);
        $leaveType->save();

        LeaveBalance::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'period_year' => 2026,
            'allocated_days' => $quota,
            'used_days' => 0,
            'reserved_days' => 0,
            'active_from' => '2026-01-01',
            'active_to' => '2026-12-31',
        ]);

        return $leaveType;
    }

    public function test_action_context_filter_separates_actionable_and_waiting_others(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd', 'HR Peninjau');
        $manager = $this->user($company, 'supervisor', 'Supervisor Langsung');
        $staff = $this->user($company, 'staff', 'Staff Pemohon');
        $staff->forceFill(['manager_id' => $manager->id])->save();

        // 2-step workflow: Step 1 = manager, Step 2 = HR
        $steps = [
            ['label' => 'Atasan Langsung', 'type' => 'manager', 'fallback_id' => $hr->id],
            ['label' => 'HR Approval', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $manager->id],
        ];
        $this->actingAs($hr)->post('/approval-workflows', [
            'kind' => 'permission',
            'expected_id' => 0,
            'confirm' => 1,
            'steps' => $steps,
        ])->assertRedirect();

        $permission = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-02',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'duration_days' => 1,
            'reason' => 'Keperluan keluarga',
            'is_approved' => false,
            'status' => 'pending',
        ]);
        app(ApprovalWorkflow::class)->attach($permission);
        $permission->save();

        // Manager should see permission in actionable, but not in waiting_others
        $this->actingAs($manager)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1)
            ->assertSee('Tinjau pengajuan');

        $this->actingAs($manager)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 0);

        $this->actingAs($manager)->get('/permissions?action_context=all')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1);

        // HR is reviewer for Step 2, so at Step 0, HR sees 0 actionable, 1 waiting_others
        $this->actingAs($hr)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 0);

        $this->actingAs($hr)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1)
            ->assertSee('Detail');

        // Self-approval prohibition: Staff cannot see their own request in actionable
        $this->actingAs($staff)->get('/permissions?action_context=actionable')
            ->assertForbidden(); // Staff without managerial role cannot access workspace review list

        // Manager creates own permission: Manager must NOT see own permission in actionable
        $ownPermission = Permission::create([
            'user_id' => $manager->id,
            'date_permission' => '2026-11-05',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'duration_days' => 1,
            'reason' => 'Cuti pribadi supervisor',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);

        $this->actingAs($manager)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', function ($items) use ($ownPermission) {
                return ! $items->contains('id', $ownPermission->id);
            });
    }

    public function test_action_context_pagination_and_company_isolation(): void
    {
        $companyA = $this->company('Perusahaan A');
        $companyB = $this->company('Perusahaan B');

        $managerA = $this->user($companyA, 'supervisor', 'Manager A');
        $adminA = $this->user($companyA, 'admin', 'Admin A');
        $adminB = $this->user($companyB, 'admin', 'Admin B');

        // Create 15 pending permissions in company A under manager A
        for ($i = 1; $i <= 15; $i++) {
            $staff = $this->user($companyA, 'staff', "Staff A $i");
            $staff->forceFill(['manager_id' => $managerA->id])->save();
            Permission::create([
                'user_id' => $staff->id,
                'date_permission' => '2026-11-10',
                'reason' => "Izin A $i",
                'is_approved' => false,
                'status' => 'pending',
                'approval_version' => 0,
            ]);
        }

        // Create 5 permissions in company B
        for ($i = 1; $i <= 5; $i++) {
            $staffB = $this->user($companyB, 'staff', "Staff B $i");
            Permission::create([
                'user_id' => $staffB->id,
                'date_permission' => '2026-11-10',
                'reason' => "Izin B $i",
                'is_approved' => false,
                'status' => 'pending',
                'approval_version' => 0,
            ]);
        }

        // Manager A viewing actionable page 1 (10 per page)
        $this->actingAs($managerA)->get('/permissions?action_context=actionable&page=1')
            ->assertOk()
            ->assertViewHas('permissions', function ($items) {
                return $items->total() === 15 && $items->count() === 10;
            });

        // Manager A viewing actionable page 2
        $this->actingAs($managerA)->get('/permissions?action_context=actionable&page=2')
            ->assertOk()
            ->assertViewHas('permissions', function ($items) {
                return $items->total() === 15 && $items->count() === 5;
            });

        // Admin B should never see company A permissions
        $this->actingAs($adminB)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', function ($items) {
                return $items->total() === 5;
            })
            ->assertDontSee('Perusahaan A')
            ->assertDontSee('Staff A 1');
    }

    public function test_fallback_reviewer_transitions_to_actionable_when_primary_is_inactive(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd', 'HR Backup');
        $primarySupervisor = $this->user($company, 'supervisor', 'Supervisor Utama');
        $staff = $this->user($company, 'staff', 'Staff Demo');
        $staff->forceFill(['manager_id' => $primarySupervisor->id])->save();

        $steps = [
            ['label' => 'Atasan Langsung', 'type' => 'manager', 'fallback_id' => $hr->id],
        ];
        $this->actingAs($hr)->post('/approval-workflows', [
            'kind' => 'permission',
            'expected_id' => 0,
            'confirm' => 1,
            'steps' => $steps,
        ])->assertRedirect();

        $permission = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-12',
            'reason' => 'Acara dinas',
            'is_approved' => false,
            'status' => 'pending',
        ]);
        app(ApprovalWorkflow::class)->attach($permission);
        $permission->save();

        // Initially, primary supervisor is active
        $this->actingAs($primarySupervisor)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1);

        $this->actingAs($hr)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 0);

        // Deactivate primary supervisor
        $primarySupervisor->forceFill(['employment_status' => 'inactive'])->save();

        // Now fallback reviewer (HR) sees it in actionable queue
        $this->actingAs($hr)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1)
            ->assertSee('Tinjau pengajuan');

        // And inactive primary supervisor is logged out and redirected to login
        $this->actingAs($primarySupervisor)->get('/permissions?action_context=actionable')
            ->assertRedirect(route('login'));
    }

    public function test_cancellation_review_flow_permissions_and_balance_restoration(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd', 'HRD Manager');
        $supervisor = $this->user($company, 'supervisor', 'Supervisor Direct');
        $otherSupervisor = $this->user($company, 'supervisor', 'Supervisor Lain');
        $staff = $this->user($company, 'staff', 'Staff Cuti');
        $staff->forceFill(['manager_id' => $supervisor->id])->save();

        $leaveType = $this->setupLeaveTypeWithBalance($company, $staff, 12);

        // Create approved permission deducting 3 days
        $permission = Permission::create([
            'user_id' => $staff->id,
            'leave_type_id' => $leaveType->id,
            'date_permission' => '2026-11-03',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-05',
            'duration_days' => 3,
            'reason' => 'Cuti liburan',
            'is_approved' => true,
            'status' => 'approved',
            'approval_version' => 1,
            'leave_policy_snapshot' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'deducts_balance' => true,
                'requires_attachment' => false,
            ],
        ]);

        // Manually record used balance in balance table
        $balance = LeaveBalance::where('user_id', $staff->id)->first();
        $balance->forceFill(['used_days' => 3])->save();

        $permission->refresh();

        // Staff requests cancellation
        $res = $this->actingAs($staff)->post('/submission-cancellations/permission/'.$permission->id, [
            'approval_version' => $permission->approval_version,
            'request_cancellation' => 1,
            'decision_reason' => 'Rencana liburan dibatalkan tiket ditunda.',
        ]);
        $res->assertRedirect();

        $this->assertSame('cancellation_requested', $permission->fresh()->status);

        // Verification of reviewer authorization for cancellation
        $approvalService = app(SubmissionApproval::class);
        $this->assertTrue($approvalService->canDecide($supervisor, $permission->fresh()));
        $this->assertTrue($approvalService->canDecide($hr, $permission->fresh()));
        $this->assertFalse($approvalService->canDecide($otherSupervisor, $permission->fresh()));
        $this->assertFalse($approvalService->canDecide($staff, $permission->fresh()));

        // Case 1: Supervisor rejects cancellation request
        $this->actingAs($supervisor)->postJson('/submission-decisions/permission/'.$permission->id, [
            'status' => 'rejected',
            'approval_version' => $permission->fresh()->approval_version,
            'decision_reason' => 'Proyek mendesak, tetap cuti atau ajukan ulang.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $permission->fresh()->status);
        $this->assertTrue((bool) $permission->fresh()->is_approved);
        // Balance remains used
        $this->assertSame(3, (int) $balance->fresh()->used_days);

        // Verify classified event is cancellation_rejected
        $history = $approvalService->history('permission', $permission->fresh());
        $lastEvent = $history[0];
        $this->assertSame('cancellation_rejected', $lastEvent->classified['event_type']);
        $this->assertSame('Pembatalan ditolak; pengajuan tetap disetujui', $lastEvent->classified['action_label']);

        // Case 2: Staff requests cancellation again
        $this->actingAs($staff)->post('/submission-cancellations/permission/'.$permission->id, [
            'approval_version' => $permission->fresh()->approval_version,
            'request_cancellation' => 1,
            'decision_reason' => 'Kondisi darurat, mohon dibatalkan.',
        ])->assertRedirect();

        $this->assertSame('cancellation_requested', $permission->fresh()->status);

        // HR approves cancellation
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$permission->id, [
            'status' => 'approved',
            'approval_version' => $permission->fresh()->approval_version,
            'decision_reason' => 'Disetujui dibatalkan, kuota dipulihkan.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $permission->fresh()->status);
        $this->assertFalse((bool) $permission->fresh()->is_approved);

        // Balance used_days should be restored to 0
        $this->assertSame(0, (int) $balance->fresh()->used_days);

        // Verify classified event is cancellation_approved
        $historyAfter = $approvalService->history('permission', $permission->fresh());
        $approvedCancelEvent = $historyAfter[0];
        $this->assertSame('cancellation_approved', $approvedCancelEvent->classified['event_type']);
        $this->assertSame('Pembatalan disetujui', $approvedCancelEvent->classified['action_label']);
    }

    public function test_overtime_and_reimbursement_action_context_filtering(): void
    {
        $company = $this->company();
        $manager = $this->user($company, 'supervisor', 'Supervisor Overtime');
        $staff = $this->user($company, 'staff', 'Staff Overtime');
        $staff->forceFill(['manager_id' => $manager->id])->save();

        $overtime = Overtime::create([
            'user_id' => $staff->id,
            'date' => '2026-11-15',
            'duration' => 120,
            'status' => 'pending',
            'approval_version' => 0,
        ]);

        $reimbursement = Reimbursement::create([
            'user_id' => $staff->id,
            'date' => '2026-11-15',
            'amount' => 150000,
            'description' => 'Makan malam lembur',
            'status' => 'pending',
            'approval_version' => 0,
        ]);

        // Manager should see both in actionable
        $this->actingAs($manager)->get('/overtimes?action_context=actionable')
            ->assertOk()
            ->assertViewHas('overtimes', fn ($items) => $items->total() === 1)
            ->assertSee('Tinjau pengajuan');

        $this->actingAs($manager)->get('/reimbursements?action_context=actionable')
            ->assertOk()
            ->assertViewHas('reimbursements', fn ($items) => $items->total() === 1)
            ->assertSee('Tinjau pengajuan');

        // Other user cannot see in actionable
        $other = $this->user($company, 'supervisor', 'Supervisor Unrelated');
        $this->actingAs($other)->get('/overtimes?action_context=actionable')
            ->assertOk()
            ->assertViewHas('overtimes', fn ($items) => $items->total() === 0);

        $this->actingAs($other)->get('/reimbursements?action_context=actionable')
            ->assertOk()
            ->assertViewHas('reimbursements', fn ($items) => $items->total() === 0);
    }

    public function test_impact_preview_and_cannot_decide_reasons(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd', 'HR User');
        $manager = $this->user($company, 'supervisor', 'Manager User');
        $staff = $this->user($company, 'staff', 'Staff User');
        $staff->forceFill(['manager_id' => $manager->id])->save();

        $leaveType = $this->setupLeaveTypeWithBalance($company, $staff, 12);

        $permission = Permission::create([
            'user_id' => $staff->id,
            'leave_type_id' => $leaveType->id,
            'date_permission' => '2026-11-20',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
            'duration_days' => 2,
            'reason' => 'Keperluan penting',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
            'leave_policy_snapshot' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'deducts_balance' => true,
            ],
        ]);

        $service = app(SubmissionApproval::class);

        // Impact preview for pending permission approval
        $approvePreview = $service->impactPreview('permission', $permission, 'approved');
        $this->assertStringContainsString('cadangan cuti (2 hari) menjadi saldo terpakai', $approvePreview['impact']);

        // Impact preview for pending permission rejection
        $rejectPreview = $service->impactPreview('permission', $permission, 'rejected');
        $this->assertStringContainsString('cadangan cuti (2 hari) dilepas kembali', $rejectPreview['impact']);

        // Check whyCannotDecide for self-approval
        $reasonSelf = $service->whyCannotDecide($staff, $permission);
        $this->assertStringContainsString('milik Anda sendiri', $reasonSelf);

        // Check whyCannotDecide for inactive reviewer
        $inactiveManager = $this->user($company, 'supervisor', 'Inactive Mgr');
        $inactiveManager->forceFill(['employment_status' => 'inactive'])->save();
        $reasonInactive = $service->whyCannotDecide($inactiveManager, $permission);
        $this->assertStringContainsString('nonaktif', $reasonInactive);
    }

    public function test_legacy_needs_review_is_not_actionable_and_does_not_render_decision_form(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin', 'Admin HR');
        $staff = $this->user($company, 'staff', 'Staff Legacy');

        $legacyPermission = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-10-10',
            'reason' => 'Data lama sebelum approval',
            'is_approved' => false,
            'status' => 'needs_review',
            'approval_version' => 0,
        ]);
        $legacyPermission->forceFill(['approval_legacy' => true])->save();

        $service = app(SubmissionApproval::class);

        // [P1] canDecide must return false for needs_review
        $this->assertFalse($service->canDecide($admin, $legacyPermission));
        $this->assertNull($service->actionType($admin, $legacyPermission));
        $this->assertStringContainsString('rekonsiliasi', $service->whyCannotDecide($admin, $legacyPermission));

        // [P1] Must NOT appear in actionable queue
        $this->actingAs($admin)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 0);

        // [P1] Must NOT appear in waiting_others queue
        $this->actingAs($admin)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 0);

        // Appears under 'all' with Detail action, not Tinjau pengajuan
        $this->actingAs($admin)->get('/permissions?action_context=all')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->total() === 1)
            ->assertSee('Detail')
            ->assertDontSee('Tinjau pengajuan');

        // On show page, no decision form is rendered for needs_review
        $this->actingAs($admin)->get('/permissions/'.$legacyPermission->id)
            ->assertOk()
            ->assertDontSee('id="submission-decision-form"', false)
            ->assertDontSee('Tinjau keputusan')
            ->assertSee('Data Legacy')
            ->assertSee('rekonsiliasi');

        // [P1] Direct mutation endpoint MUST reject needs_review
        $this->actingAs($admin)->postJson('/submission-decisions/permission/'.$legacyPermission->id, [
            'status' => 'approved',
            'approval_version' => 0,
            'decision_reason' => 'Bypass attempt',
        ])->assertConflict();

        $this->actingAs($admin)->postJson('/submission-decisions/permission/'.$legacyPermission->id, [
            'status' => 'rejected',
            'approval_version' => 0,
            'decision_reason' => 'Bypass attempt',
        ])->assertConflict();

        $this->assertSame('needs_review', $legacyPermission->fresh()->status);
    }

    public function test_empty_workflow_and_null_reviewers_and_archived_fallback_sql_resolution(): void
    {
        $company = $this->company();
        $manager = $this->user($company, 'supervisor', 'Supervisor Direct');
        $fallback = $this->user($company, 'supervisor', 'Supervisor Fallback');
        $otherSupervisor = $this->user($company, 'supervisor', 'Supervisor Unrelated');
        $supervisorSubmitter = $this->user($company, 'supervisor', 'Supervisor Submitter');
        $supervisorSubmitter->forceFill(['manager_id' => $manager->id])->save();
        $staff = $this->user($company, 'staff', 'Staff Submitter');
        $staff->forceFill(['manager_id' => $manager->id])->save();

        // Case 1: Workflow [] is treated as without workflow
        $emptyWfRow = Permission::create([
            'user_id' => $supervisorSubmitter->id,
            'date_permission' => '2026-11-26',
            'reason' => 'Empty workflow row',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);
        $emptyWfRow->forceFill(['approval_steps' => []])->save();

        // Direct manager can decide single-step (no workflow)
        $this->actingAs($manager)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->contains('id', $emptyWfRow->id));

        // Submitter cannot decide own request, but sees it in waiting_others
        $this->actingAs($supervisorSubmitter)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => ! $items->contains('id', $emptyWfRow->id));

        $this->actingAs($supervisorSubmitter)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->contains('id', $emptyWfRow->id));

        // Unrelated supervisor cannot see or decide submissions from other teams
        $this->actingAs($otherSupervisor)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => ! $items->contains('id', $emptyWfRow->id));

        $this->actingAs($otherSupervisor)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => ! $items->contains('id', $emptyWfRow->id));

        // Case 2: Null reviewer_id and null fallback_id must NOT be lost due to SQL NULL three-valued logic
        $nullReviewersRow = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-27',
            'reason' => 'Null reviewers row',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);
        $nullReviewersRow->forceFill([
            'approval_steps' => [['label' => 'Empty Step', 'type' => 'user', 'reviewer_id' => null, 'fallback_id' => null]],
            'approval_step' => 0,
        ])->save();

        // Neither manager nor other supervisor is actionable
        $this->actingAs($manager)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => ! $items->contains('id', $nullReviewersRow->id));

        // CRITICAL CHECK: Must appear in waiting_others, not dropped by SQL NULL
        $this->actingAs($manager)->get('/permissions?action_context=waiting_others')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->contains('id', $nullReviewersRow->id));

        // Case 3: Primary reviewer has archived_at set -> fallback reviewer becomes actionable
        $archivedReviewer = $this->user($company, 'supervisor', 'Archived Supervisor');
        $archivedReviewer->forceFill(['archived_at' => now()->subDay()])->save();

        $archivedPrimaryRow = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-28',
            'reason' => 'Archived primary row',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);
        $archivedPrimaryRow->forceFill([
            'approval_steps' => [['label' => 'Step with Archived Primary', 'type' => 'user', 'reviewer_id' => $archivedReviewer->id, 'fallback_id' => $fallback->id]],
            'approval_step' => 0,
            'approval_reviewer_ids' => [$archivedReviewer->id, $fallback->id],
        ])->save();

        // Fallback reviewer must see it in actionable queue
        $this->actingAs($fallback)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->contains('id', $archivedPrimaryRow->id));

        // Case 4: Primary reviewer belongs to a different company -> fallback reviewer becomes actionable
        $foreignCompany = $this->company('Perusahaan Lain');
        $foreignReviewer = $this->user($foreignCompany, 'supervisor', 'Foreign Supervisor');

        $foreignPrimaryRow = Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-29',
            'reason' => 'Foreign primary row',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);
        $foreignPrimaryRow->forceFill([
            'approval_steps' => [['label' => 'Step with Foreign Primary', 'type' => 'user', 'reviewer_id' => $foreignReviewer->id, 'fallback_id' => $fallback->id]],
            'approval_step' => 0,
            'approval_reviewer_ids' => [$foreignReviewer->id, $fallback->id],
        ])->save();

        // Fallback reviewer must see it in actionable queue
        $this->actingAs($fallback)->get('/permissions?action_context=actionable')
            ->assertOk()
            ->assertViewHas('permissions', fn ($items) => $items->contains('id', $foreignPrimaryRow->id));
    }

    public function test_action_context_is_executed_directly_in_sql_without_loading_candidates_into_memory(): void
    {
        $company = $this->company();
        $manager = $this->user($company, 'supervisor', 'Supervisor SQL');
        $staff = $this->user($company, 'staff', 'Staff SQL');
        $staff->forceFill(['manager_id' => $manager->id])->save();

        Permission::create([
            'user_id' => $staff->id,
            'date_permission' => '2026-11-25',
            'reason' => 'Test SQL Pagination',
            'is_approved' => false,
            'status' => 'pending',
            'approval_version' => 0,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $paginator = Permission::visibleTo($manager)
            ->withActionContext($manager, 'actionable')
            ->paginate(10);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(1, $paginator->total());

        // Verify exactly 2 DML queries are run on permissions: 1 count query and 1 select query with limit/offset
        $permissionQueries = array_values(array_filter($queries, fn ($q) => preg_match('/from [`"]?permissions[`"]?/i', $q['query'])));
        $this->assertCount(2, $permissionQueries);
        $this->assertStringContainsString('count(*)', strtolower($permissionQueries[0]['query']));
        $this->assertStringContainsString('limit 10', strtolower($permissionQueries[1]['query']));

        // Verify neither query performed an unrestricted get() of candidates
        foreach ($permissionQueries as $q) {
            $this->assertStringNotContainsString('status in', str_replace(['`', '"'], '', strtolower($q['query'])));
        }
    }
}
