<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', fn (Blueprint $t) => $t->string('status', 24)->default('pending'));
        DB::table('permissions')->where('is_approved', true)->update(['status' => 'approved']);
        DB::table('permissions')->where('is_approved', false)->update(['status' => 'needs_review']);
        // Widen the existing enum without rewriting its values; cancellation becomes representable.
        Schema::table('overtimes', fn (Blueprint $t) => $t->string('status', 24)->default('pending')->change());
        foreach (['permissions', 'overtimes', 'reimbursements'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('approval_version')->default(0);
                $t->boolean('approval_legacy')->default(false);
                $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->timestamp('reviewed_at')->nullable();
                $t->string('decision_reason', 1000)->nullable();
            });
            DB::table($table)->update(['approval_legacy' => true]);
        }
        Schema::create('submission_approval_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('kind', 24);
            $t->unsignedBigInteger('submission_id');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version');
            $t->string('from_status', 24);
            $t->string('to_status', 24);
            $t->string('reason', 1000);
            $t->json('snapshot');
            $t->timestamp('created_at');
            $t->unique(['kind', 'submission_id', 'version']);
            $t->index(['company_id', 'kind', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_approval_events');
        foreach (['permissions', 'overtimes', 'reimbursements'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['reviewed_by']);
                $t->dropColumn(['approval_version', 'approval_legacy', 'reviewed_by', 'reviewed_at', 'decision_reason']);
            });
        }
        Schema::table('permissions', fn (Blueprint $t) => $t->dropColumn('status'));
        // Keep overtime status as varchar: existing cancelled values cannot fit the old enum.
    }
};
