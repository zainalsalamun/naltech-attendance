<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->string('timezone', 64)->nullable());
        Schema::create('work_calendar_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->date('effective_from');
            $t->json('weekdays');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'effective_from']);
        });
        Schema::create('work_calendar_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->boolean('is_working');
            $t->string('label', 150);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_calendar_days');
        Schema::dropIfExists('work_calendar_rules');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('timezone'));
    }
};
