<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionEvent;
use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Models\LeaveType;
use App\Models\OrganizationUnit;
use App\Models\Overtime;
use App\Models\Permission;
use App\Models\Reimbursement;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    /**
     * Run demonstration and learning dataset with realistic HR hierarchy and transactions.
     */
    public function run(): void
    {
        // Jalankan pondasi awal terlebih dahulu
        $this->call(InitialSetupSeeder::class);

        $company = Company::where('email', 'admin@naltech.id')->firstOrFail();
        $admin = User::where('email', 'superadmin@naltech.test')->firstOrFail();

        $units = OrganizationUnit::where('company_id', $company->id)->get()->keyBy('code');
        $shifts = Shift::where('company_id', $company->id)->get()->keyBy('name');

        $regularShift = $shifts->first();

        // 1. Karyawan Hierarkis Demo
        // Direktur
        $direktur = User::firstOrCreate(
            ['email' => 'direktur@naltech.test'],
            [
                'name' => 'Hendra Wijaya',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'position' => 'Direktur Operasional',
                'department' => 'Direksi & Eksekutif',
                'phone' => '081112223331',
                'company_id' => $company->id,
                'organization_unit_id' => $units['DIR']->id ?? null,
                'employee_number' => 'DIR-001',
                'employment_status' => 'active',
                'joined_on' => '2024-01-01',
                'shift_id' => $regularShift?->id,
            ]
        );

        // HR Manager
        $hrManager = User::firstOrCreate(
            ['email' => 'hr@naltech.test'],
            [
                'name' => 'Sarah Anggraini',
                'password' => Hash::make('password'),
                'role' => 'hrd',
                'position' => 'HR & General Affairs Manager',
                'department' => 'Sumber Daya Manusia',
                'phone' => '081112223332',
                'company_id' => $company->id,
                'organization_unit_id' => $units['HRD']->id ?? null,
                'manager_id' => $direktur->id,
                'employee_number' => 'HR-001',
                'employment_status' => 'active',
                'joined_on' => '2024-03-01',
                'shift_id' => $regularShift?->id,
            ]
        );

        // Supervisor Operasional
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@naltech.test'],
            [
                'name' => 'Bambang Pamungkas',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'position' => 'Supervisor Operasional Lapangan',
                'department' => 'Operasional & Layanan',
                'phone' => '081112223333',
                'company_id' => $company->id,
                'organization_unit_id' => $units['OPS']->id ?? null,
                'manager_id' => $hrManager->id,
                'employee_number' => 'SPV-001',
                'employment_status' => 'active',
                'joined_on' => '2025-01-15',
                'shift_id' => $regularShift?->id,
            ]
        );

        // Staff Operasional
        $staff = User::firstOrCreate(
            ['email' => 'staff@naltech.test'],
            [
                'name' => 'Andi Pratama',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'position' => 'Staff Teknisi & Layanan',
                'department' => 'Operasional & Layanan',
                'phone' => '081112223334',
                'company_id' => $company->id,
                'organization_unit_id' => $units['OPS']->id ?? null,
                'manager_id' => $supervisor->id,
                'employee_number' => 'STF-001',
                'employment_status' => 'active',
                'joined_on' => '2025-06-01',
                'shift_id' => $regularShift?->id,
            ]
        );

        // Staff Keuangan
        $staffFinance = User::firstOrCreate(
            ['email' => 'siti@naltech.test'],
            [
                'name' => 'Siti Rahmawati',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'position' => 'Staff Administrasi Keuangan',
                'department' => 'Keuangan & Akuntansi',
                'phone' => '081112223335',
                'company_id' => $company->id,
                'organization_unit_id' => $units['FIN']->id ?? null,
                'manager_id' => $hrManager->id,
                'employee_number' => 'FIN-001',
                'employment_status' => 'active',
                'joined_on' => '2025-08-01',
                'shift_id' => $regularShift?->id,
            ]
        );

        // 2. Alokasi Kuota Cuti Tahunan 2026
        $annualLeave = LeaveType::where('company_id', $company->id)->where('code', 'CT')->first();
        if ($annualLeave && Schema::hasTable('leave_balances')) {
            foreach ([$staff, $staffFinance, $supervisor] as $user) {
                $balance = LeaveBalance::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'leave_type_id' => $annualLeave->id,
                        'period_year' => 2026,
                    ],
                    [
                        'allocated_days' => 12,
                        'reserved_days' => 0,
                        'used_days' => 0,
                        'remaining_days' => 12,
                    ]
                );

                if (Schema::hasTable('leave_balance_ledgers') && $balance->ledgers()->count() === 0) {
                    LeaveBalanceLedger::create([
                        'company_id' => $company->id,
                        'leave_balance_id' => $balance->id,
                        'user_id' => $user->id,
                        'actor_id' => $hrManager->id,
                        'transaction_type' => 'initial_allocation',
                        'amount_days' => 12,
                        'previous_allocated' => 0,
                        'previous_reserved' => 0,
                        'previous_used' => 0,
                        'previous_remaining' => 0,
                        'current_allocated' => 12,
                        'current_reserved' => 0,
                        'current_used' => 0,
                        'current_remaining' => 12,
                        'reason' => 'Alokasi saldo awal cuti tahunan periode 2026.',
                        'created_at' => now(),
                    ]);
                }
            }
        }

        // 3. Catatan Kehadiran & Jejak Koreksi Audit Demo
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Absensi normal kemarin untuk Staff
        Attendance::firstOrCreate(
            ['user_id' => $staff->id, 'date' => $yesterday],
            [
                'time_in' => '07:55:00',
                'time_out' => '17:05:00',
                'latlon_in' => '-6.223840,106.831550',
                'latlon_out' => '-6.223845,106.831555',
                'checked_in_at' => Carbon::yesterday()->setTime(7, 55),
                'checked_out_at' => Carbon::yesterday()->setTime(17, 5),
                'scheduled_name' => 'Shift Reguler (08:00 - 17:00)',
                'scheduled_in' => '08:00:00',
                'scheduled_out' => '17:00:00',
                'scheduled_start_at' => Carbon::yesterday()->setTime(8, 0),
                'scheduled_end_at' => Carbon::yesterday()->setTime(17, 0),
                'attendance_timezone' => 'Asia/Jakarta',
                'calendar_working' => true,
            ]
        );

        // Absensi hari ini untuk Staff dengan jejak audit koreksi
        $attendanceToday = Attendance::firstOrCreate(
            ['user_id' => $staff->id, 'date' => $today],
            [
                'time_in' => '08:00:00',
                'time_out' => '17:00:00',
                'latlon_in' => '-6.223847,106.831557',
                'latlon_out' => '-6.223847,106.831557',
                'checked_in_at' => Carbon::today()->setTime(8, 0),
                'checked_out_at' => Carbon::today()->setTime(17, 0),
                'scheduled_name' => 'Shift Reguler (08:00 - 17:00)',
                'scheduled_in' => '08:00:00',
                'scheduled_out' => '17:00:00',
                'scheduled_start_at' => Carbon::today()->setTime(8, 0),
                'scheduled_end_at' => Carbon::today()->setTime(17, 0),
                'attendance_timezone' => 'Asia/Jakarta',
                'calendar_working' => true,
            ]
        );

        if (Schema::hasTable('attendance_correction_events') && $attendanceToday->correctionEvents()->count() === 0) {
            AttendanceCorrectionEvent::create([
                'company_id' => $company->id,
                'attendance_id' => $attendanceToday->id,
                'actor_id' => $hrManager->id,
                'reason' => 'Penyesuaian jam masuk karena fingerprint mesin lobi utama sempat maintenance jaringan.',
                'snapshot' => [
                    'before' => ['time_in' => '08:18:00', 'status' => 'late'],
                    'after' => ['time_in' => '08:00:00', 'status' => 'ontime'],
                ],
                'created_at' => now(),
            ]);
        }

        // 4. Contoh Pengajuan Izin/Cuti
        if ($annualLeave) {
            // Contoh cuti disetujui
            $permApproved = Permission::firstOrCreate(
                [
                    'user_id' => $staffFinance->id,
                    'date_permission' => '2026-03-10',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'reason' => 'Acara keluarga di luar kota.',
                    'status' => 'approved',
                    'is_approved' => 1,
                    'start_date' => '2026-03-10',
                    'end_date' => '2026-03-11',
                    'duration_days' => 2,
                    'approval_step' => 2,
                ]
            );

            // Contoh cuti pending menunggu persetujuan
            $permPending = Permission::firstOrCreate(
                [
                    'user_id' => $staff->id,
                    'date_permission' => '2026-04-05',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'reason' => 'Perpanjangan SIM dan administrasi kependudukan.',
                    'status' => 'pending',
                    'is_approved' => 0,
                    'start_date' => '2026-04-05',
                    'end_date' => '2026-04-05',
                    'duration_days' => 1,
                    'approval_step' => 0,
                    'approval_steps' => [
                        [
                            'type' => 'manager',
                            'reviewer_id' => $supervisor->id,
                            'fallback_id' => $hrManager->id,
                            'label' => 'Atasan Langsung',
                        ],
                        [
                            'type' => 'designated',
                            'reviewer_id' => $hrManager->id,
                            'fallback_id' => null,
                            'label' => 'HR / Administrator',
                        ],
                    ],
                    'approval_reviewer_ids' => [$supervisor->id, $hrManager->id],
                ]
            );
        }

        // 5. Contoh Lembur Pending
        if (Schema::hasTable('overtimes')) {
            Overtime::firstOrCreate(
                [
                    'user_id' => $staff->id,
                    'date' => $today,
                ],
                [
                    'duration' => 180, // 3 jam
                    'description' => 'Maintenance server dan backup bulanan berkala.',
                    'status' => 'pending',
                    'approval_step' => 0,
                    'approval_steps' => [
                        [
                            'type' => 'manager',
                            'reviewer_id' => $supervisor->id,
                            'fallback_id' => $hrManager->id,
                            'label' => 'Atasan Langsung',
                        ],
                    ],
                    'approval_reviewer_ids' => [$supervisor->id],
                ]
            );
        }

        // 6. Contoh Reimbursement Disetujui
        if (Schema::hasTable('reimbursements')) {
            Reimbursement::firstOrCreate(
                [
                    'user_id' => $supervisor->id,
                    'date' => $yesterday,
                    'amount' => 150000,
                ],
                [
                    'description' => 'Bahan bakar dan konsumsi tim survei lokasi proyek.',
                    'status' => 'approved',
                    'approval_step' => 1,
                ]
            );
        }
    }
}
