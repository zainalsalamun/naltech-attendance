<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->json('steps');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'kind', 'id']);
        });
        foreach (['permissions', 'overtimes', 'reimbursements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('approval_steps')->nullable();
                $table->json('approval_reviewer_ids')->nullable();
                $table->unsignedInteger('approval_step')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['permissions', 'overtimes', 'reimbursements'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['approval_steps', 'approval_reviewer_ids', 'approval_step']));
        }
        Schema::dropIfExists('approval_workflows');
    }
};
