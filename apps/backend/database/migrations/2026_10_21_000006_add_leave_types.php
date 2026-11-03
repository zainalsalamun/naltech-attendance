<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->boolean('deducts_balance');
            $table->unsignedSmallInteger('annual_quota_days')->nullable();
            $table->boolean('requires_attachment');
            $table->text('requirements')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('leave_type_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('reason', 1000);
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['leave_type_id', 'version']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('leave_policy_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_type_id');
            $table->dropColumn('leave_policy_snapshot');
        });
        Schema::dropIfExists('leave_type_events');
        Schema::dropIfExists('leave_types');
    }
};
