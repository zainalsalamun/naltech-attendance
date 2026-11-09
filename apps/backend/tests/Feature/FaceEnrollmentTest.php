<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaceEnrollmentTest extends TestCase
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

    private function member(?Company $company = null, string $role = 'staff'): User
    {
        $company ??= Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Jakarta',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'Face']);
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function submit(User $user): FaceEnrollment
    {
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/face-enrollments', [
            'consent' => true, 'photo' => UploadedFile::fake()->image('face.jpg', 480, 640),
            'status' => 'approved', 'user_id' => 999, 'company_id' => 999,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        return FaceEnrollment::findOrFail($response->json('data.id'));
    }

    public function test_submission_is_private_scoped_and_cannot_self_approve_or_duplicate(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $row = $this->submit($user);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($user->company_id, $row->company_id);
        Storage::disk('face_private')->assertExists($row->photo_path);
        $this->getJson('/api/face-enrollments')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.photo_path')->assertJsonMissingPath('data.0.user_id')
            ->assertJsonPath('attendance_face_available', false);
        $this->postJson('/api/face-enrollments', ['consent' => true, 'photo' => UploadedFile::fake()->image('second.jpg', 480, 640)])->assertConflict();
        $this->assertCount(1, Storage::disk('face_private')->allFiles());
        $this->actingAs($user)->get('/face-enrollments')->assertForbidden();
        $this->get('/face-enrollments/'.$row->id.'/photo')->assertForbidden();
        $user->update(['role' => 'admin']);
        $this->patchJson('/face-enrollments/'.$row->id, ['decision' => 'approved', 'identity_confirmed' => true])->assertForbidden();
        $this->assertSame('pending', $row->fresh()->status);
    }

    public function test_review_replacement_revocation_and_photo_cleanup_preserve_events(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $admin = $this->member($user->company, 'hrd');
        $first = $this->submit($user);
        $firstPath = $first->photo_path;
        $this->actingAs($admin)->get('/face-enrollments')->assertOk()->assertSee($user->name);
        $this->get('/face-enrollments/'.$first->id.'/photo')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->patchJson('/face-enrollments/'.$first->id, ['decision' => 'approved'])->assertUnprocessable();
        $this->patch('/face-enrollments/'.$first->id, ['decision' => 'approved', 'identity_confirmed' => true])->assertRedirect();
        $this->assertSame('approved', $first->fresh()->status);
        $this->patchJson('/face-enrollments/'.$first->id, ['decision' => 'approved', 'identity_confirmed' => true])->assertConflict();
        $second = $this->submit($user);
        $this->assertSame('approved', $first->fresh()->status);
        $this->actingAs($admin)->patch('/face-enrollments/'.$second->id, ['decision' => 'approved', 'identity_confirmed' => true])->assertRedirect();
        $this->assertSame('superseded', $first->fresh()->status);
        Storage::disk('face_private')->assertMissing($firstPath);
        $this->get('/face-enrollments/'.$first->id.'/photo')->assertNotFound();
        $secondPath = $second->photo_path;
        $this->patchJson('/face-enrollments/'.$second->id, ['decision' => 'revoked'])->assertUnprocessable();
        $this->patch('/face-enrollments/'.$second->id, ['decision' => 'revoked', 'reason' => 'Identitas perlu ditinjau ulang.'])->assertRedirect();
        $this->assertSame('revoked', $second->fresh()->status);
        Storage::disk('face_private')->assertMissing($secondPath);
        $this->assertDatabaseHas('face_enrollment_events', ['face_enrollment_id' => $second->id, 'action' => 'approved']);
        $this->assertDatabaseHas('face_enrollment_events', ['face_enrollment_id' => $second->id, 'action' => 'revoked']);
    }

    public function test_cross_company_review_photos_and_other_account_withdrawal_are_blocked(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $row = $this->submit($user);
        $outsider = $this->member(null, 'admin');
        $this->actingAs($outsider)->get('/face-enrollments')->assertOk()->assertDontSee($user->email);
        $this->get('/face-enrollments/'.$row->id.'/photo')->assertNotFound();
        $this->patchJson('/face-enrollments/'.$row->id, ['decision' => 'approved', 'identity_confirmed' => true])->assertNotFound();
        $colleague = $this->member($user->company);
        Sanctum::actingAs($colleague);
        $this->deleteJson('/api/face-enrollments/'.$row->id)->assertNotFound();
        $this->getJson('/api/face-enrollments')->assertJsonCount(0, 'data');
        Sanctum::actingAs($user);
        $path = $row->photo_path;
        $this->deleteJson('/api/face-enrollments/'.$row->id)->assertOk();
        Storage::disk('face_private')->assertMissing($path);
        $this->assertSame('revoked', $row->fresh()->status);
    }

    public function test_invalid_photos_consent_and_legacy_embedding_are_rejected(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        Sanctum::actingAs($user);
        foreach ([UploadedFile::fake()->image('small.jpg', 40, 40), UploadedFile::fake()->createWithContent('fake.jpg', '<svg/>'), UploadedFile::fake()->image('large.jpg', 480, 640)->size(2049)] as $photo) {
            $this->postJson('/api/face-enrollments', ['consent' => true, 'photo' => $photo])->assertUnprocessable();
        }
        $this->postJson('/api/face-enrollments', ['photo' => UploadedFile::fake()->image('face.jpg', 480, 640)])->assertUnprocessable();
        $this->postJson('/api/update-profile', ['face_embedding' => '1,2,3'])->assertGone();
        $this->assertDatabaseCount('face_enrollments', 0);
        $user->forceFill(['face_embedding' => 'private-legacy-data'])->save();
        $this->getJson('/api/user')->assertOk()->assertJsonMissingPath('face_embedding');
        $this->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8, 'is_match' => true])->assertStatus(503);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_matching_preview_is_opt_in_and_never_authorizes_attendance(): void
    {
        Storage::fake('face_private');
        Http::preventStrayRequests();
        $user = $this->member();
        Sanctum::actingAs($user);
        $photo = fn () => ['photo' => UploadedFile::fake()->image('probe.jpg', 480, 640)];
        $this->postJson('/api/face-matching-preview', $photo())->assertStatus(503);
        Http::assertNothingSent();
        config(['face.preview_enabled' => true, 'face.provider' => 'self_hosted', 'face.self_hosted_key' => 'fixture-only-secret']);
        $this->postJson('/api/face-matching-preview', $photo())->assertConflict();
        $row = $this->submit($user);
        $row->update(['status' => 'approved']);
        Http::fake(['127.0.0.1:8021/*' => Http::response([
            'engine' => 'opencv-sface', 'similarity' => 0.9, 'reference_faces' => 1,
            'probe_faces' => 1, 'liveness' => 'not_configured', 'attendance_eligible' => false,
        ])]);
        $this->postJson('/api/face-matching-preview', $photo())->assertOk()
            ->assertJsonPath('data.matched', true)->assertJsonPath('data.attendance_eligible', false)
            ->assertJsonMissingPath('data.similarity');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer fixture-only-secret')
            && isset($request['reference'], $request['probe']) && ! isset($request['user_id']));
        $this->postJson('/api/checkin', ['latitude' => -6.2, 'longitude' => 106.8, 'is_match' => true])->assertStatus(503);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_untrusted_provider_responses_and_revoked_enrollment_fail_closed(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $row = $this->submit($user);
        $row->update(['status' => 'approved']);
        config(['face.preview_enabled' => true, 'face.provider' => 'self_hosted', 'face.self_hosted_key' => 'fixture-only-secret']);
        $photo = fn () => ['photo' => UploadedFile::fake()->image('probe.jpg', 480, 640)];
        foreach ([['engine' => 'wrong', 'similarity' => .9], ['engine' => 'opencv-sface', 'similarity' => '0.9'], ['engine' => 'opencv-sface', 'similarity' => .9, 'reference_faces' => 1, 'probe_faces' => 1, 'liveness' => 'passed', 'attendance_eligible' => true]] as $payload) {
            Http::fake(['*' => Http::response($payload)]);
            $this->postJson('/api/face-matching-preview', $photo())->assertStatus(503);
        }
        $row->update(['status' => 'revoked']);
        $this->postJson('/api/face-matching-preview', $photo())->assertConflict();
    }

    public function test_rejection_requires_reason_and_storage_failure_leaves_no_enrollment(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $admin = $this->member($user->company, 'admin');
        $row = $this->submit($user);
        $path = $row->photo_path;
        $this->actingAs($admin)->patchJson('/face-enrollments/'.$row->id, ['decision' => 'rejected'])->assertUnprocessable();
        $this->patch('/face-enrollments/'.$row->id, ['decision' => 'rejected', 'reason' => 'Foto kurang jelas.'])->assertRedirect();
        $this->assertSame('rejected', $row->fresh()->status);
        Storage::disk('face_private')->assertMissing($path);
        Sanctum::actingAs($user);
        $disk = \Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('face_private')->once()->andReturn($disk);
        $this->postJson('/api/face-enrollments', ['consent' => true, 'photo' => UploadedFile::fake()->image('face.jpg', 480, 640)])->assertStatus(503);
        $this->assertDatabaseCount('face_enrollments', 1);
        $this->assertDatabaseCount('face_enrollment_events', 2);
    }

    public function test_maintenance_expires_pending_photos_but_preserves_approved_reference(): void
    {
        Storage::fake('face_private');
        $user = $this->member();
        $approved = $this->submit($user);
        $approved->update(['status' => 'approved']);
        $pending = $this->submit($user);
        $pending->forceFill(['created_at' => now()->subDays(31)])->save();
        $expiredPath = $pending->photo_path;
        Storage::disk('face_private')->put('orphan-old.jpg', 'fixture');
        touch(Storage::disk('face_private')->path('orphan-old.jpg'), now()->subDays(2)->timestamp);
        Storage::disk('face_private')->put('orphan-recent.jpg', 'fixture');
        $this->artisan('face:purge-photos')->assertSuccessful();
        $this->assertSame('expired', $pending->fresh()->status);
        Storage::disk('face_private')->assertMissing($expiredPath);
        Storage::disk('face_private')->assertMissing('orphan-old.jpg');
        Storage::disk('face_private')->assertExists('orphan-recent.jpg');
        Storage::disk('face_private')->assertExists($approved->photo_path);
        $this->assertDatabaseHas('face_enrollment_events', ['face_enrollment_id' => $pending->id, 'action' => 'expired']);
    }

    public function test_unassigned_and_supervisor_accounts_cannot_review_faces(): void
    {
        $unassigned = User::factory()->create();
        Sanctum::actingAs($unassigned);
        $this->getJson('/api/face-enrollments')->assertForbidden();
        $supervisor = $this->member(null, 'supervisor');
        $this->actingAs($supervisor)->get('/face-enrollments')->assertForbidden();
        $hr = $this->member($supervisor->company, 'hrd');
        $this->get('/users/'.$hr->id.'/edit')->assertForbidden();
        $this->putJson('/users/'.$hr->id, ['name' => 'Overwrite', 'email' => $hr->email, 'password' => 'new-password'])->assertForbidden();
        $this->delete('/users/'.$hr->id)->assertForbidden();
        $this->putJson('/users/'.$supervisor->id, ['name' => $supervisor->name, 'email' => $supervisor->email, 'role' => 'admin'])->assertForbidden();
        $this->postJson('/users', ['name' => 'Escalated', 'email' => 'escalated@example.test', 'password' => 'new-password', 'role' => 'hrd', 'employee_number' => 'EMP-ESC'])->assertForbidden();
        $this->assertSame('supervisor', $supervisor->fresh()->role);
    }

    public function test_unmigrated_feature_is_unavailable_before_route_binding_queries_tables(): void
    {
        $user = $this->member(null, 'admin');
        \Illuminate\Support\Facades\Schema::drop('face_enrollment_events');
        \Illuminate\Support\Facades\Schema::drop('face_enrollments');
        Sanctum::actingAs($user);
        $this->getJson('/api/face-enrollments')->assertStatus(503);
        $this->deleteJson('/api/face-enrollments/123')->assertStatus(503);
        $this->actingAs($user)->get('/face-enrollments/123/photo')->assertStatus(503);
        $this->getJson('/api/user')->assertOk();
    }
}
