<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('effective_from');
            $t->string('shift_name')->nullable();
            $t->time('time_in')->nullable();
            $t->time('time_out')->nullable();
            $t->string('timezone', 64);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('reason', 1000);
            $t->timestamp('created_at');
            $t->index(['company_id', 'user_id', 'effective_from', 'id'], 'employee_shift_effective_index');
        });
        Schema::create('attendance_correction_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('reason', 1000);
            $t->json('snapshot');
            $t->timestamp('created_at');
        });
        Schema::table('attendances', function (Blueprint $t) {
            $t->unsignedBigInteger('schedule_assignment_id')->nullable();
            $t->string('schedule_source', 20)->nullable();
            $t->string('scheduled_name')->nullable();
            $t->time('scheduled_in')->nullable();
            $t->time('scheduled_out')->nullable();
            $t->string('attendance_timezone', 64)->nullable();
            $t->boolean('calendar_working')->nullable();
            foreach (['scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'checked_out_at'] as $field) {
                $t->dateTime($field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_events');
        Schema::table('attendances', fn (Blueprint $t) => $t->dropColumn(['schedule_assignment_id', 'schedule_source', 'scheduled_name', 'scheduled_in', 'scheduled_out', 'attendance_timezone', 'calendar_working', 'scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'checked_out_at']));
        Schema::dropIfExists('employee_shift_assignments');
    }
};
