<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Default runs pristine initial setup.
     * Use `--class=DemoDataSeeder` or set `SEED_DEMO_DATA=true` to populate rich demo fixtures.
     */
    public function run(): void
    {
        if (env('SEED_DEMO_DATA', false)) {
            $this->call(DemoDataSeeder::class);
        } else {
            $this->call(InitialSetupSeeder::class);
        }
    }
}
