<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeavePolicyTest extends TestCase
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
        $company = Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Fixture', 'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '.5', 'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);

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
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['code' => 'annual', 'name' => 'Cuti tahunan QA', 'is_active' => 1, 'deducts_balance' => 1, 'annual_quota_days' => 12, 'requires_attachment' => 0, 'requirements' => 'Koordinasikan dengan atasan.', 'version' => 0, 'change_reason' => 'Kebijakan fixture'], $extra);
    }

    private function type(User $admin, array $extra = []): LeaveType
    {
        $this->actingAs($admin)->post('/leave-types', $this->data($extra))->assertRedirect()->assertSessionHasNoErrors();

        return LeaveType::where('company_id', $admin->company_id)->latest('id')->firstOrFail();
    }

    private function allocate(User $admin, User $target, LeaveType $type, int $days = 12): void
    {
        app(\App\Services\LeaveBalanceService::class)->allocate($admin, $target, $type, 2026, $days, 'Alokasi awal fixture');
    }

    public function test_master_scope_normalization_audit_and_stale_edit(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $supervisor = $this->user($company, 'supervisor');
        $staff = $this->user($company);
        $foreign = $this->user($this->company(), 'hrd');
        $type = $this->type($admin);
        $this->assertSame('ANNUAL', $type->code);
        $this->assertDatabaseHas('leave_type_events', ['leave_type_id' => $type->id, 'version' => 1, 'actor_id' => $admin->id]);
        $this->postJson('/leave-types', $this->data())->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson('/leave-types/'.$type->id, $this->data(['version' => 1, 'name' => 'Tahunan revisi', 'is_active' => 0]))->assertRedirect();
        $this->putJson('/leave-types/'.$type->id, $this->data(['version' => 1]))->assertConflict();
        $this->assertDatabaseCount('leave_type_events', 2);
        $this->get('/leave-types/'.$type->id.'/edit')->assertOk()->assertSee('Tahunan revisi')->assertSee('Riwayat kebijakan terbaru');
        $this->actingAs($supervisor)->get('/leave-types')->assertOk()->assertDontSee('Tambah jenis cuti');
        $this->get('/leave-types/create')->assertForbidden();
        $this->postJson('/leave-types', $this->data())->assertForbidden();
        $this->actingAs($staff)->get('/leave-types')->assertForbidden();
        $this->actingAs($foreign)->get('/leave-types/'.$type->id.'/edit')->assertNotFound();
        $this->putJson('/leave-types/'.$type->id, $this->data(['version' => 2]))->assertNotFound();
        $this->actingAs($admin)->deleteJson('/leave-types/'.$type->id)->assertStatus(405);
    }

    public function test_quota_requires_explicit_policy_and_no_seeded_entitlements(): void
    {
        $admin = $this->user($this->company(), 'hrd');
        $this->assertDatabaseCount('leave_types', 0);
        $this->actingAs($admin)->get('/leave-types')->assertOk()->assertSee('Belum ada jenis cuti sesuai filter');
        $this->actingAs($admin)->postJson('/leave-types', $this->data(['annual_quota_days' => null, 'deducts_balance' => true]))->assertUnprocessable()->assertJsonValidationErrors('annual_quota_days');
        $this->postJson('/leave-types', $this->data(['annual_quota_days' => 367]))->assertUnprocessable();
        $this->postJson('/leave-types', $this->data(['annual_quota_days' => 1.5]))->assertUnprocessable();
        $this->postJson('/leave-types', $this->data(['change_reason' => '   ']))->assertUnprocessable();
        $this->postJson('/leave-types', $this->data(['code' => 'a b']))->assertUnprocessable();
        $type = $this->type($admin, ['deducts_balance' => false, 'annual_quota_days' => 12]);
        $this->assertNull($type->annual_quota_days);
        $this->assertFalse($type->deducts_balance);
        $this->type($admin, ['code' => 'ZERO', 'annual_quota_days' => 0]);
        $this->assertDatabaseCount('leave_type_events', 2);
    }

    public function test_api_only_lists_own_active_types_and_requires_real_attachment_before_writing(): void
    {
        Storage::fake('submission_private');
        $company = $this->company();
        $admin = $this->user($company, 'hrd');
        $owner = $this->user($company);
        $type = $this->type($admin, ['requires_attachment' => 1]);
        $this->allocate($admin, $owner, $type, 12);
        $inactive = $this->type($admin, ['code' => 'INACTIVE', 'is_active' => 0]);
        $foreignAdmin = $this->user($this->company(), 'hrd');
        $foreignType = $this->type($foreignAdmin);
        $this->actingAs($owner)->getJson('/api/leave-types')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $type->id)->assertJsonPath('data.0.requires_attachment', true);
        $data = ['date' => '2026-10-21', 'reason' => 'Pengajuan cuti', 'leave_type_id' => $type->id];
        $this->postJson('/api/api-permissions', $data)->assertUnprocessable()->assertJsonValidationErrors('image');
        foreach ([$inactive->id, $foreignType->id, 999999] as $invalid) {
            $this->postJson('/api/api-permissions', array_replace($data, ['leave_type_id' => $invalid, 'image' => UploadedFile::fake()->image('proof.jpg')]))->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
        }
        $this->assertDatabaseCount('permissions', 0);
        $this->assertCount(0, Storage::disk('submission_private')->allFiles());
        $response = $this->postJson('/api/api-permissions', $data + ['image' => UploadedFile::fake()->image('proof.jpg'), 'status' => 'approved', 'is_approved' => true, 'leave_policy_snapshot' => ['name' => 'Forged', 'requires_attachment' => false]]);
        $response->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.leave_policy_snapshot.name', $type->name)->assertJsonPath('data.leave_policy_snapshot.requires_attachment', true)->assertJsonPath('data.leave_policy_snapshot.version', 1);
        $row = Permission::findOrFail($response->json('data.id'));
        $this->assertStringStartsWith('private:', $row->image);
        $this->assertCount(1, Storage::disk('submission_private')->allFiles());
    }

    public function test_policy_snapshot_survives_updates_and_uses_existing_two_stage_approval(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd');
        $manager = $this->user($company, 'supervisor');
        $backup = $this->user($company, 'admin');
        $owner = $this->user($company);
        $owner->forceFill(['manager_id' => $manager->id])->save();
        $type = $this->type($hr);
        $this->allocate($hr, $owner, $type, 12);
        $steps = [['label' => 'Atasan', 'type' => 'manager', 'fallback_id' => $backup->id], ['label' => 'HR', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $backup->id]];
        $this->postJson('/approval-workflows', ['kind' => 'permission', 'expected_id' => 0, 'steps' => $steps, 'confirm' => 1])->assertRedirect();
        $response = $this->actingAs($owner)->postJson('/api/api-permissions', ['date' => '2026-10-21', 'reason' => 'Cuti', 'leave_type_id' => $type->id]);
        $response->assertCreated()->assertJsonPath('data.approval_progress.total', 2);
        $id = $response->json('data.id');
        $this->actingAs($hr)->put('/leave-types/'.$type->id, $this->data(['version' => 1, 'name' => 'Aturan baru', 'annual_quota_days' => 20, 'requires_attachment' => 1, 'is_active' => 0]))->assertRedirect();
        $row = Permission::findOrFail($id);
        $this->assertSame(12, $row->leave_policy_snapshot['annual_quota_days']);
        $this->assertSame('Cuti tahunan QA', $row->leave_policy_snapshot['name']);
        $data = ['status' => 'approved', 'approval_version' => 0, 'decision_reason' => 'Aturan saat dikirim sesuai'];
        $this->postJson('/submission-decisions/permission/'.$id, $data)->assertForbidden();
        $this->actingAs($manager)->postJson('/submission-decisions/permission/'.$id, $data)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->actingAs($hr)->postJson('/submission-decisions/permission/'.$id, array_replace($data, ['approval_version' => 1]))->assertOk()->assertJsonPath('data.status', 'approved');
        $this->get('/permissions/'.$id)->assertOk()->assertSee('Cuti tahunan QA')->assertSee('Kuota kebijakan: 12');
        $snapshot = json_decode(DB::table('submission_approval_events')->where('submission_id', $id)->where('kind', 'permission')->first()->snapshot, true);
        $this->assertSame($type->id, $snapshot['before']['leave_type_id']);
        $this->actingAs($owner)->getJson('/api/api-permissions/'.$id)->assertOk()->assertJsonPath('data.leave_policy_snapshot.version', 1)->assertJsonCount(2, 'data.approval_history');
    }

    public function test_general_permission_stays_unclassified_and_web_submission_is_for_actor_only(): void
    {
        $company = $this->company();
        $hr = $this->user($company, 'hrd');
        $owner = $this->user($company);
        $legacy = Permission::create(['user_id' => $owner->id, 'date_permission' => '2026-10-20', 'reason' => 'Lama', 'is_approved' => false]);
        $this->type($hr);
        $this->assertNull($legacy->fresh()->leave_type_id);
        $this->actingAs($owner)->postJson('/api/api-permissions', ['date' => '2026-10-21', 'reason' => 'Izin umum'])->assertCreated()->assertJsonPath('data.leave_type_id', null)->assertJsonPath('data.leave_policy_snapshot', null);
        $this->actingAs($hr)->get('/permissions/create')->assertOk()->assertSee('Izin umum (bukan cuti)');
        $this->post('/permissions', ['date' => '2026-10-22', 'reason' => 'Pengajuan HR', 'user_id' => $owner->id])->assertRedirect();
        $this->assertSame($hr->id, Permission::latest('id')->first()->user_id);
        $this->get('/permissions')->assertOk()->assertSee('Jenis cuti');
    }

    public function test_unmigrated_policy_schema_fails_closed(): void
    {
        $owner = $this->user($this->company(), 'admin');
        Schema::drop('leave_type_events');
        $this->actingAs($owner)->get('/leave-types')->assertStatus(503);
        $this->getJson('/api/leave-types')->assertStatus(503);
        $this->postJson('/api/api-permissions', ['date' => '2026-10-21', 'reason' => 'Tidak boleh tanpa schema'])->assertStatus(503);
        $this->assertDatabaseCount('permissions', 0);
    }
}
