<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));

        return $app;
    }

    private function member(): User
    {
        $company = Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Jakarta',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);

        DB::table('work_calendar_rules')->insert([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create(['role' => 'staff', 'phone' => '081234567', 'image_url' => 'images/users/original.png']);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    public function test_submissions_belong_to_current_account_and_start_unapproved(): void
    {
        Storage::fake('submission_private');
        $user = $this->member();
        Sanctum::actingAs($user);
        $this->postJson('/api/api-permissions', ['date' => '2026-10-20', 'reason' => 'Keperluan keluarga', 'user_id' => 999, 'is_approved' => true])
            ->assertCreated()->assertJsonPath('data.user_id', $user->id)->assertJsonPath('data.is_approved', false);
        $this->postJson('/api/api-reimbursements', ['date' => '2026-10-20', 'description' => 'Transportasi', 'amount' => '150000.50', 'status' => 'approved', 'user_id' => 999])
            ->assertCreated()->assertJsonPath('data.user_id', $user->id)->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('reimbursements', ['user_id' => $user->id, 'amount' => '150000.50']);
        $this->assertDatabaseHas('permissions', ['user_id' => $user->id, 'image' => null]);
    }

    public function test_lists_are_paginated_and_foreign_details_are_not_exposed(): void
    {
        $user = $this->member();
        $other = $this->member();
        $colleague = User::factory()->create();
        $colleague->forceFill(['company_id' => $user->company_id])->save();
        foreach ([\App\Models\Permission::class, \App\Models\Reimbursement::class] as $model) {
            $permission = $model === \App\Models\Permission::class;
            $fields = $permission ? ['date_permission' => '2026-10-20', 'reason' => 'Keperluan'] : ['date' => '2026-10-20', 'description' => 'Biaya', 'amount' => 100];
            foreach (range(1, 22) as $index) {
                $model::create($fields + ['user_id' => $user->id]);
            }
            $foreign = $model::create($fields + ['user_id' => $other->id]);
            $sameCompany = $model::create($fields + ['user_id' => $colleague->id]);
            Sanctum::actingAs($user);
            $path = '/api/'.($permission ? 'api-permissions' : 'api-reimbursements');
            $this->getJson($path)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
            $this->getJson($path.'?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.user_id', $user->id);
            $this->getJson($path.'/'.$foreign->id)->assertNotFound();
            $this->getJson($path.'/'.$sameCompany->id)->assertNotFound();
            $own = $model::where('user_id', $user->id)->firstOrFail();
            $this->getJson($path.'/'.$own->id)->assertOk()->assertJsonPath('data.user_id', $user->id);
            $this->getJson($path.'?page=0')->assertUnprocessable();
        }
    }

    public function test_invalid_dates_descriptions_amounts_and_uploads_do_not_create_records(): void
    {
        Storage::fake('public');
        $user = $this->member();
        Sanctum::actingAs($user);
        $this->postJson('/api/api-permissions', ['date' => '2026-02-30', 'reason' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'reason']);
        foreach (['0', '-1', '10000000000', '1.234', 'NaN', '150.000,50'] as $amount) {
            $this->postJson('/api/api-reimbursements', ['date' => '2026-10-20', 'description' => 'Biaya', 'amount' => $amount])
                ->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        foreach (['api-permissions' => ['reason' => 'Keperluan'], 'api-reimbursements' => ['description' => 'Biaya', 'amount' => '1.25']] as $path => $fields) {
            foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'), UploadedFile::fake()->image('large.png')->size(2049)] as $image) {
                $this->postJson('/api/'.$path, $fields + ['date' => '2026-10-20', 'image' => $image])
                    ->assertUnprocessable()->assertJsonValidationErrors('image');
            }
        }
        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseCount('reimbursements', 0);
    }

    public function test_valid_upload_is_saved_and_storage_failure_does_not_create_row(): void
    {
        Storage::fake('submission_private');
        $user = $this->member();
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/api-permissions', ['date' => '2026-10-20', 'reason' => 'Keperluan', 'image' => UploadedFile::fake()->image('proof.png')])->assertCreated();
        $response->assertJsonMissingPath('data.image');
        Storage::disk('submission_private')->assertExists(substr(Permission::findOrFail($response->json('data.id'))->image, 8));
        $response = $this->postJson('/api/api-reimbursements', ['date' => '2026-10-20', 'description' => 'Biaya', 'amount' => '1.25', 'image' => UploadedFile::fake()->image('proof.jpg')])->assertCreated();
        Storage::disk('submission_private')->assertExists(substr(Reimbursement::findOrFail($response->json('data.id'))->image, 8));
        $disk = \Mockery::mock();
        $disk->shouldReceive('putFileAs')->twice()->andReturn(false);
        Storage::shouldReceive('disk')->with('submission_private')->twice()->andReturn($disk);
        foreach (['api-permissions' => ['reason' => 'Keperluan'], 'api-reimbursements' => ['description' => 'Biaya', 'amount' => '1.25']] as $path => $fields) {
            $this->postJson('/api/'.$path, $fields + ['date' => '2026-10-21', 'image' => UploadedFile::fake()->image('proof.png')])->assertStatus(503);
        }
        $this->assertDatabaseCount('permissions', 1);
        $this->assertDatabaseCount('reimbursements', 1);
    }

    public function test_unassigned_accounts_cannot_use_submission_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        foreach (['api-permissions', 'api-reimbursements'] as $path) {
            $this->getJson('/api/'.$path)->assertForbidden();
            $this->postJson('/api/'.$path, ['date' => '2026-10-20'])->assertForbidden();
        }
    }
}
