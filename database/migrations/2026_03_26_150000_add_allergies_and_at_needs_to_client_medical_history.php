<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_medical_history', function (Blueprint $table) {
            if (! Schema::hasColumn('client_medical_history', 'allergies')) {
                $table->json('allergies')->nullable()->after('immunization_records');
            }
            if (! Schema::hasColumn('client_medical_history', 'assistive_technology_needs')) {
                $table->json('assistive_technology_needs')->nullable()->after('allergies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_medical_history', function (Blueprint $table) {
            $cols = ['allergies', 'assistive_technology_needs'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('client_medical_history', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
