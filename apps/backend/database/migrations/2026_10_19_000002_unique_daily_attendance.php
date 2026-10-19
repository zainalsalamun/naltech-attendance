<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('attendances')->select('user_id', 'date')
            ->groupBy('user_id', 'date')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Duplikasi absensi ditemukan. Tinjau user_id/date yang ganda sebelum migrasi; tidak ada data yang dihapus otomatis.');
        }

        Schema::table('attendances', fn (Blueprint $table) =>
            $table->unique(['user_id', 'date'], 'attendances_user_date_unique'));
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropUnique('attendances_user_date_unique'));
    }
};
