<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'shifts', 'qr_absens'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            });
        }

        // Never guess ownership when the existing database has multiple companies.
        if (DB::table('companies')->count() === 1) {
            $companyId = DB::table('companies')->value('id');
            foreach (['users', 'shifts', 'qr_absens'] as $name) {
                DB::table($name)->whereNull('company_id')->update(['company_id' => $companyId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'shifts', 'qr_absens'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('company_id'));
        }
    }
};
