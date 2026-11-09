<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\QrAbsen;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrWorkspaceTest extends TestCase
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

    private function qr(Company $company, string $date, string $in = 'IN-EXAMPLE', ?string $out = 'OUT-EXAMPLE'): QrAbsen
    {
        $qr = new QrAbsen(['date' => $date, 'qr_checkin' => $in, 'qr_checkout' => $out]);
        $qr->company_id = $company->id;
        $qr->save();

        return $qr;
    }

    public function test_generation_at_month_end_is_idempotent_and_preserves_existing_codes(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(12, 0));
        $existing = $this->qr($company, '2026-02-01');
        $partial = $this->qr($company, '2026-02-02', 'KEEP-IN', null);
        $this->actingAs($admin)->post('/qr_absens', ['month' => '2026-02'])->assertRedirect(route('qr_absens.index', ['month' => '2026-02']));
        $this->assertDatabaseCount('qr_absens', 28);
        $this->assertSame('IN-EXAMPLE', $existing->fresh()->qr_checkin);
        $this->assertSame('KEEP-IN', $partial->fresh()->qr_checkin);
        $this->assertNotEmpty($partial->fresh()->qr_checkout);
        $codes = QrAbsen::orderBy('date')->get(['qr_checkin', 'qr_checkout'])->toArray();
        $this->post('/qr_absens', ['month' => '2026-02'])->assertRedirect();
        $this->assertDatabaseCount('qr_absens', 28);
        $this->assertSame($codes, QrAbsen::orderBy('date')->get(['qr_checkin', 'qr_checkout'])->toArray());
        $new = QrAbsen::where('date', '2026-02-03')->first();
        $this->assertNotSame($new->qr_checkin, $new->qr_checkout);
        $this->assertSame(32, strlen($new->qr_checkin));
    }

    public function test_filters_details_pdf_and_company_scope(): void
    {
        $company = $this->company();
        $admin = $this->member($company, 'Admin', 'admin');
        foreach (range(1, 12) as $day) {
            $this->qr($company, sprintf('2026-10-%02d', $day));
        }
        $other = $this->company();
        $foreign = $this->qr($other, '2026-10-19');
        $record = QrAbsen::where('company_id', $company->id)->first();
        $this->actingAs($admin)->get('/qr_absens?month=2026-10&page=2')->assertOk()->assertViewHas('qrAbsen', fn ($rows) => $rows->total() === 12 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'month=2026-10'));
        $this->get('/qr_absens?month=2026-10&date=2026-10-01')->assertOk()->assertViewHas('qrAbsen', fn ($rows) => $rows->total() === 1);
        $this->get('/qr_absens?date=2026-11-01')->assertOk()->assertSee('Tidak ada QR absensi');
        $this->get('/qr_absens/'.$record->id)->assertOk()->assertSee('data:image/png;base64', false);
        $pdf = $this->get('/qr-absens/'.$record->id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get('/qr_absens/'.$foreign->id)->assertNotFound();
        $this->get('/qr-absens/'.$foreign->id.'/download')->assertNotFound();
        $partial = $this->qr($company, '2026-10-21', 'IN-PARTIAL', null);
        $this->get('/qr_absens/'.$partial->id)->assertOk()->assertSee('Pasangan QR belum lengkap');
        $this->get('/qr-absens/'.$partial->id.'/download')->assertUnprocessable();
        $this->getJson('/qr_absens?month=invalid')->assertUnprocessable();
        $this->postJson('/qr_absens', ['month' => 'invalid'])->assertUnprocessable();
        $this->actingAs($this->member($company, 'Staf'))->get('/qr_absens')->assertForbidden();
    }
}
