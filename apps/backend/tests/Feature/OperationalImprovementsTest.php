<?php

namespace Tests\Feature;

use App\Mail\ApprovalStatusNotification;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use App\Services\SubmissionApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Contract\Messaging;
use Mockery;
use Tests\TestCase;

class OperationalImprovementsTest extends TestCase
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

    private function seedCompanyWithWorkflow(): array
    {
        $company = Company::create([
            'name' => 'PT Maju',
            'email' => 'hr@ptmaju.test',
            'address' => 'Jakarta',
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

        $hr = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'hrd',
            'name' => 'HR Manager',
            'email' => 'hr.manager@ptmaju.test',
            'fcm_token' => 'fcm-hr-token',
            'employment_status' => 'active',
        ]);

        $supervisor = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'supervisor',
            'name' => 'Supervisor Tim',
            'email' => 'spv@ptmaju.test',
            'fcm_token' => 'fcm-spv-token',
            'employment_status' => 'active',
        ]);

        $staff = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'staff',
            'name' => 'Budi Santoso',
            'email' => 'budi@ptmaju.test',
            'fcm_token' => 'fcm-staff-token',
            'manager_id' => $supervisor->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($hr)->post('/approval-workflows', [
            'kind' => 'reimbursement',
            'expected_id' => 0,
            'confirm' => 1,
            'steps' => [
                ['label' => 'Atasan Langsung', 'type' => 'manager', 'fallback_id' => $hr->id],
                ['label' => 'HR Manager', 'type' => 'user', 'reviewer_id' => $hr->id, 'fallback_id' => $supervisor->id],
            ],
        ])->assertRedirect();

        return compact('company', 'hr', 'supervisor', 'staff');
    }

    public function test_approval_lifecycle_sends_email_and_fcm_notifications(): void
    {
        Mail::fake();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->times(4)->andReturn([]);
        $this->app->instance(Messaging::class, $messaging);

        ['supervisor' => $supervisor, 'hr' => $hr, 'staff' => $staff] = $this->seedCompanyWithWorkflow();

        // 1. Submission created -> notifies stage 1 (supervisor)
        $reimbursement = Reimbursement::create([
            'user_id' => $staff->id,
            'date' => '2026-11-18',
            'amount' => 150000,
            'description' => 'Transportasi dinas',
            'status' => 'pending',
        ]);

        Mail::assertSent(ApprovalStatusNotification::class, function (ApprovalStatusNotification $mail) use ($supervisor) {
            return $mail->hasTo($supervisor->email) && str_contains($mail->notificationTitle, 'Baru Menunggu Tinjauan');
        });

        // 2. Stage 1 approved -> notifies stage 2 (HR) + owner progress
        $reimbursement->refresh();
        app(SubmissionApproval::class)->decide(
            $supervisor,
            'reimbursement',
            $reimbursement->id,
            'approved',
            (int) $reimbursement->approval_version,
            'Sesuai tugas dinas'
        );

        Mail::assertSent(ApprovalStatusNotification::class, function (ApprovalStatusNotification $mail) use ($hr) {
            return $mail->hasTo($hr->email) && str_contains($mail->notificationTitle, 'Menunggu Tinjauan');
        });
        Mail::assertSent(ApprovalStatusNotification::class, function (ApprovalStatusNotification $mail) use ($staff) {
            return $mail->hasTo($staff->email) && str_contains($mail->notificationTitle, 'Progres Pengajuan');
        });

        // 3. Stage 2 final approved -> notifies owner final decision
        $reimbursement->refresh();
        app(SubmissionApproval::class)->decide(
            $hr,
            'reimbursement',
            $reimbursement->id,
            'approved',
            (int) $reimbursement->approval_version,
            'Disetujui pencairan'
        );

        Mail::assertSent(ApprovalStatusNotification::class, function (ApprovalStatusNotification $mail) use ($staff) {
            return $mail->hasTo($staff->email) && str_contains($mail->notificationTitle, 'Disetujui');
        });
    }

    public function test_fcm_failure_does_not_abort_approval_decision(): void
    {
        Mail::fake();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->andThrow(new \RuntimeException('FCM unreachable'));
        $this->app->instance(Messaging::class, $messaging);

        ['supervisor' => $supervisor, 'staff' => $staff] = $this->seedCompanyWithWorkflow();

        $reimbursement = Reimbursement::create([
            'user_id' => $staff->id,
            'date' => '2026-11-18',
            'amount' => 50000,
            'description' => 'Parkir',
            'status' => 'pending',
        ]);

        $reimbursement->refresh();
        $decided = app(SubmissionApproval::class)->decide(
            $supervisor,
            'reimbursement',
            $reimbursement->id,
            'rejected',
            (int) $reimbursement->approval_version,
            'Bukti kurang jelas'
        );

        $this->assertSame('rejected', $decided->status);
    }

    public function test_web_forms_render_searchable_employee_dropdown_attribute(): void
    {
        ['hr' => $hr] = $this->seedCompanyWithWorkflow();

        $this->actingAs($hr)
            ->get(route('effective-shifts.index'))
            ->assertOk()
            ->assertSee('data-searchable-select="true"', false);

        $this->actingAs($hr)
            ->get(route('reimbursements.create'))
            ->assertOk()
            ->assertSee('data-searchable-select="true"', false);
    }

    public function test_pdf_attachment_can_be_uploaded_and_served_privately(): void
    {
        Storage::fake('submission_private');
        Mail::fake();

        ['company' => $otherCompany] = $this->seedCompanyWithWorkflow();
        $staff = User::where('email', 'budi@ptmaju.test')->firstOrFail();

        $pdf = UploadedFile::fake()->create('surat-sakit.pdf', 120, 'application/pdf');

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/api-permissions', [
                'start_date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'reason' => 'Sakit demam',
                'image' => $pdf,
            ])
            ->assertCreated();

        $permission = Permission::where('user_id', $staff->id)->firstOrFail();
        $this->assertStringEndsWith('.pdf', (string) $permission->image);

        // Owner can download PDF privately
        $response = $this->actingAs($staff)
            ->get(route('submission-attachments.show', ['kind' => 'permission', 'id' => $permission->id]));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // User from another company is forbidden
        $outsiderCompany = Company::create([
            'name' => 'PT Luar',
            'email' => 'hr@ptluar.test',
            'address' => 'Bandung',
            'latitude' => '-6.9',
            'longitude' => '107.6',
            'radius_km' => '.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'attendance_type' => 'None',
        ]);
        $outsider = User::factory()->create([
            'company_id' => $outsiderCompany->id,
            'role' => 'admin',
            'employment_status' => 'active',
        ]);

        $this->actingAs($outsider)
            ->get(route('submission-attachments.show', ['kind' => 'permission', 'id' => $permission->id]))
            ->assertNotFound();
    }

    public function test_invalid_or_oversized_pdf_attachment_is_rejected(): void
    {
        Storage::fake('submission_private');
        $this->seedCompanyWithWorkflow();
        $staff = User::where('email', 'budi@ptmaju.test')->firstOrFail();

        $tooBig = UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf');

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/api-reimbursements', [
                'date' => '2026-11-18',
                'amount' => 100000,
                'description' => 'Tiket kereta',
                'image' => $tooBig,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }
}
