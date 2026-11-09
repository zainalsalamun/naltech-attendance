<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileProfileSessionTest extends TestCase
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
        $user = User::factory()->create(['role' => 'staff', 'phone' => '081234567', 'image_url' => 'images/users/original.png']);
        $user->forceFill(['company_id' => $company->id])->save();

        return $user;
    }

    private function fields(User $user): array
    {
        return ['id' => $user->id, 'name' => 'Nama Baru', 'email' => $user->email, 'phone' => '081234999'];
    }

    public function test_own_profile_updates_only_editable_fields_and_preserves_old_photo(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);
        $this->getJson('/api/api-user/'.$user->id)->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/api-user/edit', $this->fields($user) + ['role' => 'admin', 'company_id' => 999])
            ->assertOk()->assertJsonPath('data.name', 'Nama Baru');
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('images/users/original.png', $user->fresh()->image_url);
        $this->assertSame($user->role, $user->fresh()->role);
        $this->assertSame($user->company_id, $user->fresh()->company_id);
    }

    public function test_foreign_profile_and_duplicate_email_are_rejected(): void
    {
        $user = $this->member();
        $other = $this->member();
        Sanctum::actingAs($user);
        $this->getJson('/api/api-user/'.$other->id)->assertForbidden();
        $this->postJson('/api/api-user/edit', $this->fields($other))->assertForbidden();
        $fields = $this->fields($user);
        $fields['email'] = $other->email;
        $this->postJson('/api/api-user/edit', $fields)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame($other->name, $other->fresh()->name);
    }

    public function test_photo_upload_accepts_real_image_and_rejects_svg_and_large_file(): void
    {
        Storage::fake('public');
        $user = $this->member();
        Sanctum::actingAs($user);
        foreach (['png', 'jpg'] as $extension) {
            $this->postJson('/api/api-user/edit', $this->fields($user) + ['image' => UploadedFile::fake()->image('avatar.'.$extension)])
                ->assertOk();
            Storage::disk('public')->assertExists($user->fresh()->image_url);
        }
        $image = $user->fresh()->image_url;
        foreach ([UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            UploadedFile::fake()->image('avatar.png')->size(2049)] as $invalid) {
            $this->postJson('/api/api-user/edit', $this->fields($user) + ['image' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
            $this->assertSame($image, $user->fresh()->image_url);
        }
    }

    public function test_user_without_company_can_revoke_current_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['company_id' => null])->save();
        $token = $user->createToken('mobile-test');
        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/user')->assertForbidden();
        $this->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_failed_photo_storage_does_not_save_partial_profile(): void
    {
        $user = $this->member();
        $name = $user->name;
        Sanctum::actingAs($user);
        $disk = \Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);
        $this->postJson('/api/api-user/edit', $this->fields($user) + ['image' => UploadedFile::fake()->image('avatar.png')])
            ->assertStatus(503);
        $this->assertSame($name, $user->fresh()->name);
        $this->assertSame('images/users/original.png', $user->fresh()->image_url);
    }
}
