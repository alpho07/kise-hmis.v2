<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_disabilities', function (Blueprint $table) {
            if (! Schema::hasColumn('client_disabilities', 'evidence_files')) {
                $table->json('evidence_files')->nullable()->after('assistive_technology_notes');
            }
        });

        Schema::table('intake_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('intake_assessments', 'uploaded_reports')) {
                $table->json('uploaded_reports')->nullable()->after('family_history');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_disabilities', function (Blueprint $table) {
            if (Schema::hasColumn('client_disabilities', 'evidence_files')) {
                $table->dropColumn('evidence_files');
            }
        });

        Schema::table('intake_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('intake_assessments', 'uploaded_reports')) {
                $table->dropColumn('uploaded_reports');
            }
        });
    }
};
