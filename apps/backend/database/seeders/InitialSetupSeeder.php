<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\OrganizationUnit;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run pristine production-ready setup without transactional dummy records.
     */
    public function run(): void
    {
        $password = config('initial_setup.admin_password');
        $existingAdmin = User::withoutGlobalScopes()->where('email', 'superadmin@naltech.test')->first();
        if (! $existingAdmin && (! is_string($password) || mb_strlen($password) < 12)) {
            throw new \RuntimeException('Isi SETUP_ADMIN_PASSWORD minimal 12 karakter sebelum setup awal. Tidak ada akun yang dibuat.');
        }

        // 1. Perusahaan Default
        $company = Company::firstOrCreate(
            ['email' => 'admin@naltech.id'],
            [
                'name' => 'PT Naltech Inovasi Digital',
                'address' => 'Gedung Cyber 2 Lantai 15, Jl. H.R. Rasuna Said Blok X-5, Jakarta Selatan 12950',
                'latitude' => '-6.223847',
                'longitude' => '106.831557',
                'radius_km' => '0.5',
                'time_in' => '08:00',
                'time_out' => '17:00',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        // Pastikan zona waktu perusahaan tersimpan jika ada kolom timezone
        if (Schema::hasColumn('companies', 'timezone') && empty($company->timezone)) {
            $company->update(['timezone' => 'Asia/Jakarta']);
        }

        // 2. Akun Super Administrator
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@naltech.test'],
            [
                'name' => 'Super Administrator',
                'password' => $existingAdmin?->password ?? Hash::make($password),
                'role' => 'super admin',
                'position' => 'System Administrator',
                'department' => 'Teknologi & Informasi',
                'phone' => '081234567890',
                'company_id' => $company->id,
                'employee_number' => 'ADM-001',
                'employment_status' => 'active',
                'joined_on' => '2026-01-01',
            ]
        );

        // 3. Unit Organisasi Standar
        $units = [
            ['code' => 'DIR', 'name' => 'Direksi & Eksekutif'],
            ['code' => 'HRD', 'name' => 'Sumber Daya Manusia & Umum'],
            ['code' => 'OPS', 'name' => 'Operasional & Layanan'],
            ['code' => 'TECH', 'name' => 'Teknologi & Informasi'],
            ['code' => 'FIN', 'name' => 'Keuangan & Akuntansi'],
        ];
        foreach ($units as $unit) {
            OrganizationUnit::firstOrCreate(
                ['company_id' => $company->id, 'code' => $unit['code']],
                ['name' => $unit['name'], 'is_active' => true]
            );
        }

        // Hubungkan unit organisasi Super Admin jika belum ada
        $techUnit = OrganizationUnit::where('company_id', $company->id)->where('code', 'TECH')->first();
        if ($techUnit && empty($admin->organization_unit_id)) {
            $admin->update(['organization_unit_id' => $techUnit->id]);
        }

        // 4. Master Shift Operasional
        $shifts = [
            [
                'name' => 'Shift Reguler (08:00 - 17:00)',
                'time_in' => '08:00:00',
                'time_out' => '17:00:00',
            ],
            [
                'name' => 'Shift Pagi (07:00 - 15:00)',
                'time_in' => '07:00:00',
                'time_out' => '15:00:00',
            ],
            [
                'name' => 'Shift Siang (15:00 - 23:00)',
                'time_in' => '15:00:00',
                'time_out' => '23:00:00',
            ],
            [
                'name' => 'Shift Malam (23:00 - 07:00)',
                'time_in' => '23:00:00',
                'time_out' => '07:00:00',
            ],
        ];
        foreach ($shifts as $shiftData) {
            Shift::firstOrCreate(
                ['company_id' => $company->id, 'name' => $shiftData['name']],
                $shiftData
            );
        }

        // 5. Aturan Kalender Kerja Standar (Senin - Jumat hari kerja)
        if (Schema::hasTable('work_calendar_rules')) {
            $exists = DB::table('work_calendar_rules')
                ->where('company_id', $company->id)
                ->where('effective_from', '2026-01-01')
                ->exists();

            if (! $exists) {
                DB::table('work_calendar_rules')->insert([
                    'company_id' => $company->id,
                    'effective_from' => '2026-01-01',
                    'weekdays' => json_encode([1, 2, 3, 4, 5]), // Senin s/d Jumat
                    'actor_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Master Kebijakan Jenis Cuti
        if (Schema::hasTable('leave_types')) {
            $leaveTypes = [
                [
                    'code' => 'CT',
                    'name' => 'Cuti Tahunan',
                    'is_active' => true,
                    'deducts_balance' => true,
                    'annual_quota_days' => 12,
                    'requires_attachment' => false,
                    'requirements' => 'Pengajuan diajukan selambatnya 3 hari kalender sebelum tanggal cuti dimulai.',
                ],
                [
                    'code' => 'CS',
                    'name' => 'Cuti Sakit',
                    'is_active' => true,
                    'deducts_balance' => false,
                    'annual_quota_days' => 14,
                    'requires_attachment' => true,
                    'requirements' => 'Wajib melampirkan surat keterangan sakit atau resume medis resmi dokter.',
                ],
                [
                    'code' => 'CM',
                    'name' => 'Cuti Melahirkan',
                    'is_active' => true,
                    'deducts_balance' => false,
                    'annual_quota_days' => 90,
                    'requires_attachment' => true,
                    'requirements' => 'Wajib melampirkan surat keterangan perkiraan persalinan (HPL) dari dokter/bidan.',
                ],
                [
                    'code' => 'CN',
                    'name' => 'Cuti Menikah',
                    'is_active' => true,
                    'deducts_balance' => false,
                    'annual_quota_days' => 3,
                    'requires_attachment' => false,
                    'requirements' => 'Cuti khusus bagi karyawan yang melangsungkan akad / upacara pernikahan.',
                ],
                [
                    'code' => 'IK',
                    'name' => 'Izin Kepentingan Pribadi',
                    'is_active' => true,
                    'deducts_balance' => false,
                    'annual_quota_days' => 2,
                    'requires_attachment' => false,
                    'requirements' => 'Izin mendesak untuk urusan keluarga inti atau keperluan dinas luar tak terencana.',
                ],
            ];

            foreach ($leaveTypes as $data) {
                LeaveType::firstOrCreate(
                    ['company_id' => $company->id, 'code' => $data['code']],
                    $data
                );
            }
        }

        // 7. Kebijakan Alur Persetujuan Bertingkat (Workflow Steps)
        if (Schema::hasTable('approval_workflows')) {
            $workflowSteps = [
                [
                    'type' => 'manager',
                    'reviewer_id' => null,
                    'fallback_id' => $admin->id,
                    'label' => 'Atasan Langsung',
                ],
                [
                    'type' => 'designated',
                    'reviewer_id' => $admin->id,
                    'fallback_id' => null,
                    'label' => 'HR / Administrator',
                ],
            ];

            foreach (['permission', 'overtime', 'reimbursement'] as $kind) {
                $exists = DB::table('approval_workflows')
                    ->where('company_id', $company->id)
                    ->where('kind', $kind)
                    ->exists();

                if (! $exists) {
                    DB::table('approval_workflows')->insert([
                        'company_id' => $company->id,
                        'kind' => $kind,
                        'steps' => json_encode($workflowSteps),
                        'actor_id' => $admin->id,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
}
