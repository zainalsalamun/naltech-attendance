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
        Schema::create('qr_absens', function (Blueprint $table) {
            $table->id();
            //date
            $table->date('date');
            //qr_checkin
            $table->string('qr_checkin')->nullable();
            //qr_checkout
            $table->string('qr_checkout')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_absens');
    }
};
