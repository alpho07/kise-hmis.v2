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
        if (!Schema::hasColumn('client_medical_history', 'feeding_history')) {
            Schema::table('client_medical_history', function (Blueprint $table) {
                // E5 — Feeding & Nutrition Snapshot (stored as JSON)
                $table->json('feeding_history')->nullable()->after('immunization_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_medical_history', 'feeding_history')) {
            Schema::table('client_medical_history', function (Blueprint $table) {
                $table->dropColumn('feeding_history');
            });
        }
    }
};
