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
        Schema::table('client_medical_history', function (Blueprint $table) {
            $table->json('immunization_records')->nullable()->after('perinatal_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_medical_history', function (Blueprint $table) {
            $table->dropColumn('immunization_records');
        });
    }
};
