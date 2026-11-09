<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmissionApprovalTest extends TestCase
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

    private function company(): Company
    {
        $company = Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Fixture',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $company;
    }

    private function user(Company $company, string $role = 'staff'): User
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'Password123!']);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function fields(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email];
    }

    private function submission(string $kind, User $owner)
    {
        $class = \App\Services\SubmissionApproval::model($kind);
        $data = match ($kind) {
            'permission' => ['date_permission' => '2026-10-21', 'reason' => 'Keluarga', 'is_approved' => false],'overtime' => ['date' => '2026-10-21', 'duration' => 90, 'status' => 'pending'],'reimbursement' => ['date' => '2026-10-21', 'description' => 'Transportasi', 'amount' => '12.25', 'status' => 'pending']
        };

        return $class::create(['user_id' => $owner->id] + $data);
    }

    public function test_each_kind_records_decision_and_rejects_duplicate_stale_and_final_writes(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
            $row = $this->submission($kind, $staff);
            $path = '/submission-decisions/'.$kind.'/'.$row->id;
            $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Bukti sesuai'];
            $this->actingAs($admin)->postJson($path, $data)->assertOk()->assertJsonPath('data.reviewer.name', $admin->name)->assertJsonStructure(['data' => ['decision_time']]);
            $row->refresh();
            $this->assertSame('approved', $row->status);
            $this->assertSame($admin->id, (int) $row->reviewed_by);
            $this->assertNotNull($row->reviewed_at);
            $this->assertDatabaseHas('submission_approval_events', ['kind' => $kind, 'submission_id' => $row->id, 'actor_id' => $admin->id, 'from_status' => 'pending', 'to_status' => 'approved', 'version' => 1]);
            $this->postJson($path, $data)->assertConflict();
            $this->postJson($path, ['status' => 'rejected', 'approval_version' => 1, 'decision_reason' => 'Overwrite'])->assertConflict();
            $this->postJson('/submission-cancellations/'.$kind.'/'.$row->id, ['approval_version' => 1, 'decision_reason' => 'Not owner'])->assertForbidden();
            $this->actingAs($staff)->postJson('/api/submission-cancellations/'.$kind.'/'.$row->id, ['approval_version' => 1, 'decision_reason' => 'Too late'])->assertConflict();
            if ($kind === 'permission') {
                $this->assertEquals(1, $row->is_approved);
            }
        }
        $this->assertDatabaseCount('submission_approval_events', 3);
    }

    public function test_self_review_team_scope_foreign_company_and_owner_cancellation(): void
    {
        $company = $this->company();
        $supervisor = $this->user($company, 'supervisor');
        $staff = $this->user($company);
        $colleague = $this->user($company);
        $staff->forceFill(['manager_id' => $supervisor->id])->save();
        $foreign = $this->user($this->company());
        foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
            $own = $this->submission($kind, $supervisor);
            $team = $this->submission($kind, $staff);
            $other = $this->submission($kind, $colleague);
            $outside = $this->submission($kind, $foreign);
            $data = ['status' => 'rejected', 'approval_version' => 0, 'decision_reason' => 'Tidak sesuai'];
            $prefix = '/submission-decisions/'.$kind.'/';
            $this->actingAs($supervisor)->postJson($prefix.$own->id, $data)->assertForbidden();
            $this->postJson($prefix.$team->id, $data)->assertOk();
            $this->postJson($prefix.$other->id, $data)->assertForbidden();
            $this->postJson($prefix.$outside->id, $data)->assertNotFound();
            $route = match ($kind) {
                'permission' => 'permissions','overtime' => 'overtimes','reimbursement' => 'reimbursements'
            };
            $this->get('/'.$route.'/'.$other->id)->assertNotFound();
            $this->postJson('/submission-cancellations/'.$kind.'/'.$other->id, ['approval_version' => 0, 'decision_reason' => 'Other'])->assertForbidden();
            $this->postJson('/submission-cancellations/'.$kind.'/'.$own->id, ['approval_version' => 0, 'decision_reason' => 'Tidak jadi'])->assertOk();
            $this->assertSame('cancelled', $own->fresh()->status);
            $this->actingAs($colleague)->postJson('/api/submission-cancellations/'.$kind.'/'.$other->id, ['approval_version' => 0, 'decision_reason' => 'Batal pribadi'])->assertOk();
            $this->assertDatabaseHas($other->getTable(), ['id' => $other->id, 'status' => 'cancelled']);
        }
    }

    public function test_content_edit_invalidates_decision_form_and_final_upload_cannot_change_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('submission_private');
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $row = $this->submission('overtime', $staff);
        $this->actingAs($admin)->put('/overtimes/'.$row->id, ['date' => '2026-10-21', 'duration' => 120, 'approval_version' => 0])->assertRedirect();
        $this->postJson('/submission-decisions/overtime/'.$row->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Old'])->assertConflict();
        $this->postJson('/submission-decisions/overtime/'.$row->id, ['status' => 'approved', 'approval_version' => 1, 'decision_reason' => 'New'])->assertOk();
        $this->putJson('/overtimes/'.$row->id, ['date' => '2026-10-21', 'duration' => 999, 'approval_version' => 2])->assertConflict();
        $this->assertSame(120, (int) $row->fresh()->duration);
        $event = DB::table('submission_approval_events')->where('kind', 'overtime')->where('version', 1)->first();
        $snapshot = json_decode($event->snapshot, true);
        $this->assertSame(90, (int) $snapshot['before']['duration']);
        $this->assertSame(120, (int) $snapshot['after']['duration']);
        $reimbursement = $this->submission('reimbursement', $staff);
        $this->postJson('/submission-decisions/reimbursement/'.$reimbursement->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Valid'])->assertOk();
        $this->putJson('/reimbursements/'.$reimbursement->id, ['user_id' => $staff->id, 'date' => '2026-10-21', 'amount' => '15.00', 'description' => 'Changed', 'approval_version' => 1, 'image' => \Illuminate\Http\UploadedFile::fake()->image('new.jpg')])->assertConflict();
        $this->assertNull($reimbursement->fresh()->image);
        $this->assertCount(0, \Illuminate\Support\Facades\Storage::disk('submission_private')->allFiles());
    }

    public function test_required_reason_versions_and_unmigrated_routes_fail_closed(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $row = $this->submission('permission', $this->user($company));
        $this->actingAs($admin)->postJson('/submission-decisions/permission/'.$row->id, ['status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors(['approval_version', 'decision_reason']);
        $this->postJson('/submission-decisions/permission/'.$row->id, ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => '   '])->assertUnprocessable();
        $this->assertDatabaseCount('submission_approval_events', 0);
        \Illuminate\Support\Facades\Schema::drop('submission_approval_events');
        $this->postJson('/submission-decisions/permission/999', ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'No schema'])->assertStatus(503);
        $this->get('/permissions')->assertStatus(503);
    }

    public function test_legacy_false_is_never_guessed_pending_or_rejected(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company);
        $migration = require database_path('migrations/2026_10_21_000004_add_submission_approvals.php');
        $migration->down();
        $unknown = $this->submission('permission', $staff);
        $approved = \App\Models\Permission::create(['user_id' => $staff->id, 'date_permission' => '2026-10-21', 'reason' => 'Legacy', 'is_approved' => true]);
        $migration->up();
        $unknown->refresh();
        $approved->refresh();
        $this->assertSame('needs_review', $unknown->status);
        $this->assertSame('approved', $approved->status);
        $this->assertNull($approved->reviewed_by);
        $this->assertNotNull($approved->approval_legacy);
        $this->assertDatabaseCount('submission_approval_events', 0);
        $this->actingAs($admin)->postJson('/submission-decisions/permission/'.$unknown->id, ['status' => 'rejected', 'approval_version' => 0, 'decision_reason' => 'Rekonsiliasi data lama'])->assertConflict();
        $this->assertSame('needs_review', $unknown->fresh()->status);
    }

    public function test_api_creation_ignores_forged_approval_and_returns_cancellable_version(): void
    {
        $owner = $this->user($this->company());
        $this->actingAs($owner);
        foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
            [$path,$data] = match ($kind) {
                'permission' => ['/api/api-permissions', ['date' => '2026-10-21', 'reason' => 'Family']],
                'overtime' => ['/api/api-overtimes', ['date' => '2026-10-21', 'duration' => 60]],
                'reimbursement' => ['/api/api-reimbursements', ['date' => '2026-10-21', 'description' => 'Travel', 'amount' => '12.25']]
            };
            $response = $this->postJson($path, $data + ['status' => 'approved', 'is_approved' => true, 'reviewed_by' => 999, 'approval_version' => 99]);
            $response->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.approval_version', 0)->assertJsonPath('data.reviewed_by', null);
            $id = $response->json('data.id');
            $this->postJson('/api/submission-cancellations/'.$kind.'/'.$id, ['approval_version' => 0, 'decision_reason' => 'Withdraw'])->assertOk()->assertJsonPath('data.status', 'cancelled');
            if ($kind === 'permission') {
                $this->assertEquals(0, \App\Models\Permission::findOrFail($id)->is_approved);
            }
        }
    }

    public function test_supervisor_can_read_team_evidence_on_web_and_not_other_employees(): void
    {
        \Illuminate\Support\Facades\Storage::fake('submission_private');
        $company = $this->company();
        $supervisor = $this->user($company, 'supervisor');
        $staff = $this->user($company);
        $staff->forceFill(['manager_id' => $supervisor->id])->save();
        $row = app(\App\Services\SubmissionAttachment::class)->save(new \App\Models\Permission, ['user_id' => $staff->id, 'date_permission' => '2026-10-21', 'reason' => 'Proof', 'is_approved' => false], \Illuminate\Http\UploadedFile::fake()->image('proof.png'), 'permissions');
        $this->actingAs($supervisor)->get('/submission-attachments/permission/'.$row->id)->assertOk();
        $this->getJson('/api/submission-attachments/permission/'.$row->id)->assertNotFound();
        $this->actingAs($this->user($company, 'supervisor'))->get('/submission-attachments/permission/'.$row->id)->assertNotFound();
    }

    public function test_own_overtime_list_orders_same_date_by_newest_id(): void
    {
        $staff = $this->user($this->company());
        $first = $this->submission('overtime', $staff);
        $latest = $this->submission('overtime', $staff);
        $this->actingAs($staff)->getJson('/api/api-overtimes')->assertOk()->assertJsonPath('data.0.id', $latest->id)->assertJsonPath('data.1.id', $first->id);
    }

    private function workflow(User $admin, string $kind, array $steps, int $expected = 0)
    {
        return $this->actingAs($admin)->postJson('/approval-workflows', ['kind' => $kind, 'steps' => $steps, 'expected_id' => $expected, 'confirm' => 1]);
    }

    public function test_workflow_all_kinds_enforces_order_snapshots_and_final_approval(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd');
        $manager = $this->user($company, 'supervisor');
        $backup = $this->user($company, 'admin');
        $owner = $this->user($company);
        $owner->forceFill(['manager_id' => $manager->id])->save();
        $steps = [['label' => 'Atasan', 'type' => 'manager', 'fallback_id' => $backup->id], ['label' => 'HR', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id]];
        foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
            $this->workflow($hr, $kind, $steps)->assertRedirect();
            $row = $this->submission($kind, $owner);
            $this->assertCount(2, $row->approval_steps);
            $route = '/submission-decisions/'.$kind.'/'.$row->id;
            $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Sesuai'];
            $this->actingAs($hr)->postJson($route, $data)->assertForbidden();
            $this->actingAs($backup)->postJson($route, $data)->assertForbidden();
            $this->actingAs($manager)->postJson($route, $data)->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.approval_progress.current', 2);
            if ($kind === 'permission') {
                $this->assertFalse($row->fresh()->is_approved);
            }
            $endpoint = ['permission' => 'api-permissions', 'overtime' => 'api-overtimes', 'reimbursement' => 'api-reimbursements'][$kind];
            $this->actingAs($owner)->getJson('/api/'.$endpoint.'/'.$row->id)->assertOk()->assertJsonPath('data.approval_current_reviewer', $hr->name)->assertJsonPath('data.approval_history.0.stage', 1)->assertJsonPath('data.approval_history.0.action', 'approved')->assertJsonPath('data.approval_history.0.actor', $manager->name);
            $this->actingAs($manager)->postJson($route, $data)->assertForbidden();
            $this->actingAs($hr)->postJson($route, $data)->assertConflict();
            app(\App\Services\SubmissionApproval::class);
            try {
                app(\App\Services\SubmissionApproval::class)->edit($hr, $kind, $row->id, 1, fn ($r) => $r);
                $this->fail('Partially approved content must stay locked');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            $policyId = (int) DB::table('approval_workflows')->where('kind', $kind)->max('id');
            $this->workflow($hr, $kind, [], $policyId)->assertRedirect();
            $this->assertCount(2, $row->fresh()->approval_steps);
            $this->postJson($route, array_replace($data, ['approval_version' => 1]))->assertOk()->assertJsonPath('data.status', 'approved');
            $this->assertNull($this->submission($kind, $owner)->approval_steps);
        }
        $this->assertDatabaseCount('submission_approval_events', 6);
    }

    public function test_workflow_fallback_self_review_rejection_cancellation_and_company_scope(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd');
        $manager = $this->user($company, 'supervisor');
        $backup = $this->user($company, 'supervisor');
        $owner = $this->user($company);
        $owner->forceFill(['manager_id' => $manager->id])->save();
        $steps = [['label' => 'Atasan', 'type' => 'manager', 'fallback_id' => $backup->id], ['label' => 'HR', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id]];
        $this->workflow($hr, 'permission', $steps)->assertRedirect();
        $row = $this->submission('permission', $owner);
        $manager->forceFill(['employment_status' => 'inactive'])->save();
        $this->actingAs($backup)->get('/permissions/'.$row->id)->assertOk()->assertSee('Tahap 1 dari 2')->assertSee($backup->name);
        $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Pengganti'];
        $this->postJson('/submission-decisions/permission/'.$row->id, $data)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->actingAs($owner)->postJson('/api/submission-cancellations/permission/'.$row->id, ['approval_version' => 1, 'decision_reason' => 'Batal'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $row = $this->submission('permission', $owner);
        $foreign = $this->user($this->company(), 'admin');
        $this->actingAs($foreign)->postJson('/submission-decisions/permission/'.$row->id, $data)->assertNotFound();
        $this->actingAs($backup)->postJson('/submission-decisions/permission/'.$row->id, array_replace($data, ['status' => 'rejected']))->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson('/submission-decisions/permission/'.$row->id, array_replace($data, ['approval_version' => 1]))->assertConflict();
        // Explicit reviewer who is also the owner must use the configured replacement.
        $id = (int) DB::table('approval_workflows')->where('company_id', $company->id)->max('id');
        $this->workflow($hr, 'permission', [['label' => 'HR', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id]], $id)->assertRedirect();
        $own = $this->submission('permission', $hr);
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$own->id, $data)->assertForbidden();
        $this->actingAs($backup)->postJson('/submission-decisions/permission/'.$own->id, $data)->assertOk();
    }

    public function test_workflow_configuration_validation_version_history_and_draft_form(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $other = $this->user($company, 'hrd');
        $supervisor = $this->user($company, 'supervisor');
        $foreign = $this->user($this->company(), 'admin');
        $steps = [['label' => 'HR', 'type' => 'user', 'reviewer_id' => $admin->id, 'fallback_id' => $other->id]];
        $this->workflow($supervisor, 'permission', $steps)->assertForbidden();
        $this->workflow($admin, 'permission', array_replace_recursive($steps, [0 => ['fallback_id' => $foreign->id]]))->assertUnprocessable();
        $this->workflow($admin, 'permission', array_replace_recursive($steps, [0 => ['fallback_id' => $admin->id]]))->assertUnprocessable();
        $this->workflow($admin, 'permission', $steps)->assertRedirect();
        $this->workflow($admin, 'permission', [])->assertConflict();
        $this->get('/approval-workflows')->assertOk()->assertSee('Alur persetujuan')->assertSee('Riwayat konfigurasi');
        $this->post('/approval-workflows', ['kind' => 'overtime', 'expected_id' => 0, 'confirm' => 1, 'draft' => [['label' => 'HR', 'type' => 'user', 'reviewer_id' => $admin->id, 'fallback_id' => $other->id], ['label' => '', 'type' => 'manager']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('approval_workflows', 2);
        $row = $this->submission('overtime', $other);
        $this->assertCount(1, $row->approval_steps);
    }

    public function test_missing_manager_uses_backup_and_unavailable_reviewers_never_allow_hr_bypass(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd');
        $backup = $this->user($company, 'supervisor');
        $owner = $this->user($company);
        $old = $this->submission('permission', $owner);
        $this->workflow($hr, 'permission', [['label' => 'Atasan', 'type' => 'manager', 'fallback_id' => $backup->id]])->assertRedirect();
        $this->assertNull($old->fresh()->approval_steps);
        $new = $this->submission('permission', $owner);
        $this->assertSame($backup->id, app(\App\Services\ApprovalWorkflow::class)->reviewer($new)->id);
        $backup->forceFill(['employment_status' => 'inactive'])->save();
        $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Tidak boleh lewat'];
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$new->id, $data)->assertForbidden();
        $this->assertSame('pending', $new->fresh()->status);
        $this->actingAs($owner)->getJson('/api/api-permissions/'.$new->id)->assertOk()->assertJsonPath('data.approval_current_reviewer', null)->assertJsonPath('data.approval_history', []);
        $backup->forceFill(['employment_status' => 'active'])->save();
        $this->actingAs($backup)->postJson('/submission-decisions/permission/'.$new->id, $data)->assertOk()->assertJsonPath('data.status', 'approved');
    }
}
