<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Reimbursement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReimbursementWorkspaceTest extends TestCase
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
        return Company::create(['name' => 'Kantor', 'email' => 'office@example.test', 'address' => 'Jakarta',
            'latitude' => '-6.2', 'longitude' => '106.8', 'radius_km' => '0.5',
            'time_in' => '08:00', 'time_out' => '17:00', 'attendance_type' => 'None']);
    }

    private function member(Company $company, string $name, string $role = 'staff', ?Shift $shift = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->forceFill(['company_id' => $company->id, 'shift_id' => $shift?->id])->save();

        return $user;
    }

    public function test_filters_forms_and_details_follow_company_scope(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan');
        $outsider = $this->member($this->company(), 'Rahasia luar');
        foreach (range(1, 12) as $i) {
            Reimbursement::create(['user_id' => $staff->id, 'date' => '2026-10-19', 'description' => 'Biaya transportasi', 'amount' => '125000.50', 'status' => 'pending']);
        }
        $foreign = Reimbursement::create(['user_id' => $outsider->id, 'date' => '2026-10-19', 'description' => 'Rahasia', 'amount' => 100, 'status' => 'pending']);
        $record = Reimbursement::where('user_id', $staff->id)->first();
        $this->actingAs($admin)->get('/reimbursements?name=Karyawan&date=2026-10-19&status=pending&page=2')->assertOk()->assertDontSee('Rahasia luar')
            ->assertViewHas('reimbursements', function ($rows) {
                parse_str(parse_url($rows->previousPageUrl(), PHP_URL_QUERY), $q);

                return $rows->total() === 12 && $rows->count() === 2 && $q['name'] === 'Karyawan' && $q['date'] === '2026-10-19' && $q['status'] === 'pending';
            });
        $this->get('/reimbursements/create')->assertOk()->assertDontSee('Rahasia luar');
        $this->get('/reimbursements/'.$record->id)->assertOk()->assertSee('Rp 125.000,50')->assertSee('Tidak ada bukti pengeluaran.');
        $this->get('/reimbursements/'.$record->id.'/edit')->assertOk()->assertSee('Simpan perubahan');
        $this->get('/reimbursements?name=TidakAda')->assertOk()->assertSee('Tidak ada pengajuan reimbursement');
        $this->get('/reimbursements/'.$foreign->id)->assertNotFound();
        $this->get('/reimbursements/'.$foreign->id.'/edit')->assertNotFound();
        $this->delete('/reimbursements/'.$foreign->id)->assertNotFound();
        $this->getJson('/reimbursements?status=invalid&date=invalid')->assertUnprocessable();
        $this->actingAs($staff)->get('/reimbursements')->assertForbidden();
    }

    public function test_upload_replacement_and_approval_keep_expected_files_and_values(): void
    {
        Storage::fake('submission_private');
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $this->actingAs($admin)->post('/reimbursements', ['user_id' => $admin->id, 'date' => '2026-10-19', 'description' => 'Transportasi', 'amount' => '125000.50', 'status' => 'approved', 'image' => UploadedFile::fake()->image('bukti.png')])->assertRedirect(route('reimbursements.index'));
        $record = Reimbursement::firstOrFail();
        $original = $record->image;
        Storage::disk('submission_private')->assertExists(substr($original, 8));
        $this->assertSame('pending', $record->status);
        $this->get('/reimbursements/'.$record->id)->assertOk()->assertSee('submission-attachments/reimbursement/'.$record->id)->assertDontSee('storage/');
        $data = ['user_id' => $admin->id, 'date' => '2026-10-19', 'description' => 'Biaya disetujui', 'amount' => '150000.25', 'status' => 'pending', 'approval_version' => 0];
        $this->put('/reimbursements/'.$record->id, $data)->assertRedirect();
        $this->assertSame($original, $record->fresh()->image);
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertEquals('150000.25', $record->fresh()->amount);
        $this->put('/reimbursements/'.$record->id, array_replace($data, ['approval_version' => 1, 'image' => UploadedFile::fake()->image('pengganti.jpg')]))->assertRedirect();
        $this->assertNotSame($original, $record->fresh()->image);
        Storage::disk('submission_private')->assertExists(substr($record->fresh()->image, 8));
        $this->delete('/reimbursements/'.$record->id, ['approval_version' => 2, 'decision_reason' => 'Tidak jadi'])->assertRedirect();
        $this->assertDatabaseHas('reimbursements', ['id' => $record->id, 'status' => 'cancelled']);
        Storage::disk('submission_private')->assertExists(substr($record->fresh()->image, 8));
    }

    public function test_invalid_amounts_uploads_status_and_foreign_employee_are_rejected(): void
    {
        Storage::fake('public');
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $outside = $this->member($this->company(), 'Luar');
        $this->actingAs($admin);
        $data = ['user_id' => $admin->id, 'date' => '2026-10-19', 'description' => 'Isian tetap', 'amount' => '10.25'];
        foreach (['0', '-1', '1.001', '10000000000.00', 'invalid'] as $amount) {
            $this->postJson('/reimbursements', array_replace($data, ['amount' => $amount]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson('/reimbursements', array_replace($data, ['user_id' => $outside->id]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/reimbursements', $data + ['image' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson('/reimbursements', $data + ['image' => UploadedFile::fake()->image('large.jpg')->size(2049)])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->from('/reimbursements/create')->post('/reimbursements', array_replace($data, ['amount' => 0]))->assertSessionHasErrors('amount')->assertSessionHasInput('description', 'Isian tetap');
        $this->assertDatabaseCount('reimbursements', 0);
        $record = Reimbursement::create($data + ['status' => 'pending']);
        $this->putJson('/reimbursements/'.$record->id, $data + ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson('/reimbursements/'.$record->id, array_replace($data, ['status' => 'approved', 'user_id' => $outside->id]))->assertUnprocessable();
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertEquals($admin->id, $record->fresh()->user_id);
    }

    public function test_inactive_and_archived_employees_are_rejected_on_store_and_form_is_readonly_on_edit(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $staff = $this->member($company, 'Karyawan Reimbursement');
        $this->actingAs($admin);

        // Active staff can have reimbursement created
        $this->post('/reimbursements', [
            'user_id' => $staff->id,
            'date' => '2026-10-19',
            'description' => 'Biaya dinas',
            'amount' => '50000.00',
        ])->assertRedirect(route('reimbursements.index'));
        $record = Reimbursement::firstOrFail();

        // On edit page, employee name is readonly input and user_id is hidden
        $this->get('/reimbursements/'.$record->id.'/edit')
            ->assertOk()
            ->assertSee('value="Karyawan Reimbursement" readonly', false)
            ->assertSee('type="hidden" name="user_id" value="'.$staff->id.'"', false);

        // Submitting edit with unchanged user_id successfully updates the reimbursement
        $this->put('/reimbursements/'.$record->id, [
            'user_id' => $staff->id,
            'date' => '2026-10-19',
            'description' => 'Biaya dinas yang direvisi',
            'amount' => '75000.00',
            'approval_version' => 0,
        ])->assertRedirect(route('reimbursements.index'));
        $this->assertEquals('75000.00', $record->fresh()->amount);
        $this->assertEquals('Biaya dinas yang direvisi', $record->fresh()->description);
        $this->assertEquals($staff->id, $record->fresh()->user_id);

        // Deactivate staff: cannot create new reimbursement, but history remains readable
        $staff->forceFill(['employment_status' => 'inactive', 'deactivated_at' => now()])->save();
        $this->assertFalse($staff->isEmploymentActive());

        // History remains accessible on index and show
        $this->get('/reimbursements')->assertOk()->assertSee('Karyawan Reimbursement');
        $this->get('/reimbursements/'.$record->id)->assertOk()->assertSee('Karyawan Reimbursement');

        // New reimbursement for deactivated employee must be rejected
        $this->postJson('/reimbursements', [
            'user_id' => $staff->id,
            'date' => '2026-10-20',
            'description' => 'Biaya baru',
            'amount' => '25000.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        // Archived employee must also be rejected
        $staff->forceFill(['archived_at' => now()])->save();
        $this->postJson('/reimbursements', [
            'user_id' => $staff->id,
            'date' => '2026-10-20',
            'description' => 'Biaya baru',
            'amount' => '25000.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        // create() view dropdown only includes active employees
        $this->get('/reimbursements/create')->assertOk()->assertDontSee('value="'.$staff->id.'"', false);
    }
}
