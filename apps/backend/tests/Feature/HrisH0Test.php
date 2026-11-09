<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\SubmissionAttachment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrisH0Test extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('submission_private');
    }

    private function company(): Company
    {
        $company = Company::create(['name' => 'Fixture', 'email' => 'office@example.test', 'address' => 'Fixture',
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
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    public function test_overtime_detail_requires_ownership_or_explicit_hr_role_and_company(): void
    {
        $company = $this->company();
        $owner = $this->user($company);
        $row = Overtime::create(['user_id' => $owner->id, 'date' => '2026-10-21', 'duration' => 1, 'status' => 'pending']);
        Sanctum::actingAs($owner);
        $this->getJson('/api/api-overtimes/'.$row->id)->assertOk();
        foreach (['staff', 'supervisor'] as $role) {
            Sanctum::actingAs($this->user($company, $role));
            $this->getJson('/api/api-overtimes/'.$row->id)->assertNotFound();
        }
        Sanctum::actingAs($this->user($company, 'hrd'));
        $this->getJson('/api/api-overtimes/'.$row->id)->assertOk();
        Sanctum::actingAs($this->user($this->company(), 'admin'));
        $this->getJson('/api/api-overtimes/'.$row->id)->assertNotFound();
    }

    public function test_api_routes_and_shift_validation_do_not_accept_unsupported_mutations(): void
    {
        $company = $this->company();
        $staff = $this->user($company);
        Sanctum::actingAs($staff);
        $this->postJson('/api/api-shifts', ['name' => 'X', 'time_in' => '08:00', 'time_out' => '17:00'])->assertForbidden();
        Sanctum::actingAs($this->user($company, 'admin'));
        foreach (['api-permissions', 'api-reimbursements', 'api-overtimes', 'api-shifts'] as $path) {
            $this->deleteJson('/api/'.$path.'/1')->assertStatus(405);
        }
        $this->putJson('/api/api-overtimes/1', [])->assertStatus(405);
        $this->getJson('/api/api-notes/1')->assertNotFound();
        $this->deleteJson('/api/api-notes/1')->assertNotFound();
        $this->postJson('/api/api-shifts', ['name' => 'X', 'time_in' => 'bad', 'time_out' => '17:00'])->assertUnprocessable();
        $this->postJson('/api/api-shifts', ['name' => 'X', 'time_in' => '08:00', 'time_out' => '08:00:00'])->assertUnprocessable();
        $response = $this->postJson('/api/api-shifts', ['name' => 'Night', 'time_in' => '22:00', 'time_out' => '06:00', 'company_id' => 999])->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('shifts', ['id' => $id, 'company_id' => $company->id, 'time_out' => '06:00:00']);
        $this->putJson('/api/api-shifts/'.$id, ['name' => 'Bad'])->assertUnprocessable();
        $this->postJson('/api/api-overtimes', ['date' => 'tomorrow', 'duration' => 1])->assertUnprocessable();
    }

    public function test_both_streamed_csv_exports_keep_company_scope_and_escape_formula_cells(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'admin');
        $admin->update(['name' => '=1+1']);
        $foreign = $this->user($this->company());
        $foreign->update(['name' => 'FOREIGN-SECRET']);
        foreach ([$admin, $foreign] as $user) {
            Attendance::create(['user_id' => $user->id, 'date' => '2026-10-21', 'time_in' => '08:00', 'latlon_in' => '+SUM(1,2)']);
        }
        foreach (['/attendances/export-csv', '/verifikasi/export-csv'] as $path) {
            $csv = $this->actingAs($admin)->get($path)->assertOk()->streamedContent();
            $this->assertStringContainsString("'=1+1", $csv);
            $this->assertStringContainsString("'+SUM(1,2)", $csv);
            $this->assertStringNotContainsString('FOREIGN-SECRET', $csv);
        }
    }

    public function test_private_upload_has_no_public_path_and_download_checks_owner_role_company_and_session(): void
    {
        $company = $this->company();
        $owner = $this->user($company);
        Sanctum::actingAs($owner);
        foreach (['permission' => 'api-permissions', 'reimbursement' => 'api-reimbursements'] as $kind => $endpoint) {
            $fields = $kind === 'permission' ? ['reason' => 'Reason'] : ['description' => 'Reason', 'amount' => '1.25'];
            $response = $this->postJson('/api/'.$endpoint, $fields + ['date' => '2026-10-21', 'image' => UploadedFile::fake()->image('proof.png')])->assertCreated()->assertJsonMissingPath('data.image');
            $path = $response->json('data.attachment_url');
            $this->getJson($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            Sanctum::actingAs($this->user($company));
            $this->getJson($path)->assertNotFound();
            Sanctum::actingAs($this->user($company, 'supervisor'));
            $this->getJson($path)->assertNotFound();
            Sanctum::actingAs($this->user($company, 'hrd'));
            $this->getJson($path)->assertOk();
            Sanctum::actingAs($this->user($this->company(), 'admin'));
            $this->getJson($path)->assertNotFound();
            Sanctum::actingAs($owner);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/submission-attachments/permission/1')->assertUnauthorized();
    }

    public function test_failed_database_write_removes_only_the_new_private_upload(): void
    {
        $owner = $this->user($this->company());
        Event::listen('eloquent.saving: '.Permission::class, fn () => throw new \RuntimeException('fixture'));
        try {
            app(SubmissionAttachment::class)->save(new Permission, ['user_id' => $owner->id, 'date_permission' => '2026-10-21', 'reason' => 'Reason'], UploadedFile::fake()->image('proof.png'), 'permissions');
            $this->fail('Expected fixture failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fixture', $exception->getMessage());
        }
        $this->assertDatabaseCount('permissions', 0);
        $this->assertSame([], Storage::disk('submission_private')->allFiles());
    }

    public function test_legacy_move_is_preview_first_verified_shared_and_idempotent(): void
    {
        $owner = $this->user($this->company());
        $file = UploadedFile::fake()->image('proof.png');
        $bytes = file_get_contents($file->getPathname());
        Storage::disk('public')->put('permissions/proof.png', $bytes);
        $first = Permission::create(['user_id' => $owner->id, 'date_permission' => '2026-10-21', 'reason' => 'Reason', 'image' => 'proof.png']);
        $second = Permission::create(['user_id' => $owner->id, 'date_permission' => '2026-10-22', 'reason' => 'Reason', 'image' => 'permissions/proof.png']);
        $this->artisan('submissions:privatize-attachments')->assertSuccessful();
        $this->assertSame('proof.png', $first->fresh()->image);
        Storage::disk('public')->assertExists('permissions/proof.png');
        $this->artisan('submissions:privatize-attachments --apply')->assertSuccessful();
        $image = $first->fresh()->image;
        $this->assertSame($image, $second->fresh()->image);
        $this->assertSame($bytes, Storage::disk('submission_private')->get(substr($image, 8)));
        Storage::disk('public')->assertMissing('permissions/proof.png');
        $this->artisan('submissions:privatize-attachments --apply')->assertSuccessful();
    }

    public function test_missing_legacy_files_abort_before_any_changes(): void
    {
        $owner = $this->user($this->company());
        $row = Permission::create(['user_id' => $owner->id, 'date_permission' => '2026-10-21', 'reason' => 'Reason', 'image' => '../outside.png']);
        $this->artisan('submissions:privatize-attachments --apply')->assertFailed();
        $this->assertSame('../outside.png', $row->fresh()->image);
        $this->assertSame([], Storage::disk('submission_private')->allFiles());
    }

    public function test_web_download_and_private_replacement_cleanup(): void
    {
        $company = $this->company();
        $owner = $this->user($company, 'hrd');
        $service = app(SubmissionAttachment::class);
        $row = $service->save(new Reimbursement, ['user_id' => $owner->id, 'date' => '2026-10-21', 'description' => 'Reason', 'amount' => '1.25'], UploadedFile::fake()->image('a.png'), 'reimbursements');
        $old = $row->image;
        $this->actingAs($owner)->get('/submission-attachments/reimbursement/'.$row->id)->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $row = $service->save($row, ['description' => 'Updated'], UploadedFile::fake()->image('b.png'), 'reimbursements');
        Storage::disk('submission_private')->assertMissing(substr($old, 8));
        Storage::disk('submission_private')->assertExists(substr($row->image, 8));
        $this->actingAs($this->user($company, 'supervisor'))->get('/submission-attachments/reimbursement/'.$row->id)->assertNotFound();
    }

    public function test_legacy_recovery_requires_verified_copy_and_never_loses_public_source_on_write_failure(): void
    {
        $owner = $this->user($this->company());
        $row = Permission::create(['user_id' => $owner->id, 'date_permission' => '2026-10-21', 'reason' => 'Reason', 'image' => 'proof.png']);
        $target = 'permissions/legacy-'.hash('sha256', 'permissions/proof.png').'.png';
        Storage::disk('submission_private')->put($target, 'fixture-bytes');
        $this->artisan('submissions:privatize-attachments --apply')->assertFailed();
        $this->assertSame('proof.png', $row->fresh()->image);
        Storage::disk('submission_private')->put($target.'.sha256', hash('sha256', 'fixture-bytes'));
        $this->artisan('submissions:privatize-attachments --apply')->assertSuccessful();
        $this->assertSame('private:'.$target, $row->fresh()->image);

        $row->update(['image' => 'failure.png']);
        $public = Storage::disk('public');
        $public->put('permissions/failure.png', 'fixture-bytes');
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        Storage::shouldReceive('disk')->with('submission_private')->andReturn($disk);
        $this->artisan('submissions:privatize-attachments --apply')->assertFailed();
        $public->assertExists('permissions/failure.png');
        $this->assertSame('failure.png', $row->fresh()->image);
    }
}
