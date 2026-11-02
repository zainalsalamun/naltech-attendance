<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name', 150);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $t->foreignId('manager_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['manager_id']);
            $t->dropForeign(['organization_unit_id']);
            $t->dropColumn(['manager_id', 'organization_unit_id']);
        });
        Schema::dropIfExists('organization_units');
    }
};
