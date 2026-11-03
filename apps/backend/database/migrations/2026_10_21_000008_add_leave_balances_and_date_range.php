<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'start_date')) {
                $table->date('start_date')->nullable()->after('date_permission');
            }
            if (! Schema::hasColumn('permissions', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('permissions', 'duration_days')) {
                $table->unsignedSmallInteger('duration_days')->default(1)->after('end_date');
            }
            if (! Schema::hasColumn('permissions', 'dates_breakdown')) {
                $table->json('dates_breakdown')->nullable()->after('duration_days');
            }
            if (! Schema::hasColumn('permissions', 'cancellation_reason')) {
                $table->string('cancellation_reason', 1000)->nullable()->after('decision_reason');
            }
            if (! Schema::hasColumn('permissions', 'cancellation_requested_at')) {
                $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_reason');
            }
        });

        // Backfill start_date and end_date from legacy date_permission
        DB::table('permissions')->whereNull('start_date')->update([
            'start_date' => DB::raw('date_permission'),
            'end_date' => DB::raw('date_permission'),
            'duration_days' => 1,
        ]);

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
                $table->unsignedSmallInteger('period_year');
                $table->unsignedSmallInteger('allocated_days')->default(0);
                $table->unsignedSmallInteger('reserved_days')->default(0);
                $table->unsignedSmallInteger('used_days')->default(0);
                $table->integer('remaining_days')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'user_id', 'leave_type_id', 'period_year'], 'unique_user_leave_period');
                $table->index(['company_id', 'period_year', 'leave_type_id']);
            });
        }

        if (! Schema::hasTable('leave_balance_ledgers')) {
            Schema::create('leave_balance_ledgers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_balance_id')->constrained('leave_balances')->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();
                $table->string('transaction_type', 32);
                $table->smallInteger('amount_days');
                $table->unsignedSmallInteger('previous_allocated')->default(0);
                $table->unsignedSmallInteger('previous_reserved')->default(0);
                $table->unsignedSmallInteger('previous_used')->default(0);
                $table->integer('previous_remaining')->default(0);
                $table->unsignedSmallInteger('current_allocated')->default(0);
                $table->unsignedSmallInteger('current_reserved')->default(0);
                $table->unsignedSmallInteger('current_used')->default(0);
                $table->integer('current_remaining')->default(0);
                $table->string('reason', 1000);
                $table->timestamp('created_at');

                $table->index(['company_id', 'user_id', 'leave_balance_id'], 'leave_balance_user_index');
                $table->index(['permission_id'], 'leave_ledger_permission_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_ledgers');
        Schema::dropIfExists('leave_balances');
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'end_date',
                'duration_days',
                'dates_breakdown',
                'cancellation_reason',
                'cancellation_requested_at',
            ]);
        });
    }
};
