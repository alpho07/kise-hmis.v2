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
        Schema::table('client_socio_demographics', function (Blueprint $table) {
            $table->string('primary_caregiver')->nullable()->after('household_size');
            $table->string('accessibility_at_home')->nullable()->after('socio_notes');
        });
    }

    public function down(): void
    {
        Schema::table('client_socio_demographics', function (Blueprint $table) {
            $table->dropColumn(['primary_caregiver', 'accessibility_at_home']);
        });
    }
};
