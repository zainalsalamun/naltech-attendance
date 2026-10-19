<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Overtime;
use App\Models\Reimbursement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Shifts if they don't exist
        $morning = Shift::firstOrCreate(['name' => 'Morning'], [
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
        ]);
        $afternoon = Shift::firstOrCreate(['name' => 'Afternoon'], [
            'time_in' => '13:00:00',
            'time_out' => '21:00:00',
        ]);
        $night = Shift::firstOrCreate(['name' => 'Night'], [
            'time_in' => '21:00:00',
            'time_out' => '05:00:00',
        ]);

        // 2. Create some Users with shifts
        $staffs = [];
        $names = ['Budi', 'Siti', 'Agus', 'Lani', 'Dewi', 'Rian', 'Eka', 'Fajar', 'Gita', 'Hadi'];
        foreach ($names as $index => $name) {
            $staffs[] = User::updateOrCreate(
                ['email' => strtolower($name) . '@example.com'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => 'staff',
                    'shift_id' => ($index % 3 == 0) ? $morning->id : (($index % 3 == 1) ? $afternoon->id : $night->id),
                    'position' => 'Staff Operasional',
                    'department' => 'IT Department',
                    'phone' => '0812345678' . $index,
                ]
            );
        }

        // 3. Create Attendances for Today
        $today = Carbon::today()->toDateString();
        
        // Ensure Zainal Admin has attendance today
        $admin = User::where('email', 'zainalrtf@gmail.com')->first();
        if ($admin) {
            Attendance::updateOrCreate(
                ['user_id' => $admin->id, 'date' => $today],
                [
                    'time_in' => '08:10:00', // Late
                    'latlon_in' => '-7.747033,110.355398',
                ]
            );
            
            Permission::updateOrCreate(
                ['user_id' => $admin->id, 'date_permission' => $today],
                [
                    'reason' => 'Keperluan mendesak pagi hari',
                    'is_approved' => true,
                    'created_at' => Carbon::now()->subMinutes(5),
                ]
            );
        }

        // On-time attendees (Morning shift starts at 08:00)
        foreach (range(0, 2) as $i) {
            Attendance::updateOrCreate(
                ['user_id' => $staffs[$i]->id, 'date' => $today],
                [
                    'time_in' => '07:45:00',
                    'latlon_in' => '-7.747033,110.355398',
                ]
            );
        }

        // Late attendees (Morning shift starts at 08:00)
        foreach (range(3, 5) as $i) {
            Attendance::updateOrCreate(
                ['user_id' => $staffs[$i]->id, 'date' => $today],
                [
                    'time_in' => '08:15:00',
                    'latlon_in' => '-7.747033,110.355398',
                ]
            );
        }

        // 4. Create Latest Permissions (some for today)
        $reasons = ['Sakit Demam', 'Urusan Keluarga', 'Cuti Tahunan', 'Anak Sakit', 'Kematian Kerabat'];
        foreach (range(0, 4) as $i) {
            Permission::updateOrCreate(
                ['user_id' => $staffs[$i + 4]->id, 'date_permission' => $today],
                [
                    'reason' => $reasons[$i],
                    'is_approved' => ($i % 2 == 0),
                    'created_at' => Carbon::now()->subMinutes($i * 15),
                ]
            );
        }

        // 5. Create Overtime Requests (some for today)
        foreach (range(0, 3) as $i) {
            Overtime::updateOrCreate(
                ['user_id' => $staffs[$i]->id, 'date' => $today],
                [
                    'duration' => 60 + ($i * 30),
                    'description' => 'Lembur hari ini untuk percepatan integrasi feature baru.',
                    'status' => 'pending',
                ]
            );
        }

        // 6. Create Reimbursements
        $reimbTypes = ['Bensin Service', 'Makan Lembur', 'Parkir Client'];
        foreach (range(0, 5) as $i) {
            Reimbursement::updateOrCreate(
                ['user_id' => $staffs[$i]->id, 'date' => Carbon::now()->subDays($i + 2)->toDateString()],
                [
                    'amount' => 50000 + ($i * 25000),
                    'description' => $reimbTypes[$i % 3],
                    'status' => ($i % 2 == 0) ? 'pending' : 'approved',
                    'image' => null,
                ]
            );
        }
    }
}
