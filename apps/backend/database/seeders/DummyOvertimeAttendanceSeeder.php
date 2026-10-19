<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Company;

class DummyOvertimeAttendanceSeeder extends Seeder
{
    public function run()
    {
        // 1. Ensure company has positive overtime rate so it shows Rp
        $company = Company::find(1);
        if ($company && $company->overtime_rate <= 0) {
            $company->overtime_rate = 50000;
            $company->time_in = '08:00:00';
            $company->time_out = '17:00:00';
            $company->save();
        }

        // 2. Fetch up to 10 users to insert attendance
        $users = User::take(10)->get();

        $dateToday = date('Y-m-d');
        $dateYesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($users as $user) {
            // Delete existing attendances for these days if any
            Attendance::where('user_id', $user->id)->whereIn('date', [$dateToday, $dateYesterday])->delete();

            // Insert today: Normal check-in and checkout with Overtime
            Attendance::create([
                'user_id' => $user->id,
                'date' => $dateToday,
                'time_in' => '08:00:00',
                'time_out' => '20:30:00', // overtime!
                'latlon_in' => '-6.200000,106.816666',
                'latlon_out' => '-6.200000,106.816666',
            ]);

            // Insert yesterday: Normal check-in, NO overtime
            Attendance::create([
                'user_id' => $user->id,
                'date' => $dateYesterday,
                'time_in' => '07:45:00',
                'time_out' => '17:00:00', // exactly on time
                'latlon_in' => '-6.200000,106.816666',
                'latlon_out' => '-6.200000,106.816666',
            ]);
        }
    }
}
