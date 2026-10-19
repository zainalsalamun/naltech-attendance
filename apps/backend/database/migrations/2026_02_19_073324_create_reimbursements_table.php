<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            //user_id foreign key
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            //date
            $table->date('date');
            //description
            $table->text('description');
            //amount
            $table->decimal('amount', 12, 2);
            //image
            $table->string('image')->nullable();
            //status
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursements');
    }
};
