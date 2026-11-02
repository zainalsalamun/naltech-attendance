<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->nullable();
            $table->string('employment_status', 16)->default('active');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('auth_epoch')->default(0);
            $table->unique(['company_id', 'employee_number'], 'users_company_employee_number_unique');
        });
        Schema::create('employee_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('reason', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_lifecycle_events');
        // InnoDB may use the composite unique index to support company_id's FK.
        // Keep an independent index before removing that unique constraint.
        if (Schema::getConnection()->getDriverName() === 'mysql' && ! Schema::hasIndex('users', 'users_company_rollback_index')) {
            Schema::table('users', fn (Blueprint $table) => $table->index('company_id', 'users_company_rollback_index'));
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_company_employee_number_unique');
            $table->dropColumn(['employee_number', 'employment_status', 'joined_on', 'left_on', 'deactivated_at', 'archived_at', 'auth_epoch']);
        });
    }
};
