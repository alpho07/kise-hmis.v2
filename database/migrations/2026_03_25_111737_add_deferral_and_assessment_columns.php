<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add deferral detail columns to visits
        Schema::table('visits', function (Blueprint $table) {
            $table->string('deferral_reason')->nullable()->after('deferred_reason');
            $table->text('deferral_notes')->nullable()->after('deferral_reason');
            $table->date('next_appointment_date')->nullable()->after('deferral_notes');
        });

        // Add assessment_summary column to intake_assessments
        // (the original migration only has intake_summary; assessment_summary is used by the editor)
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->text('assessment_summary')->nullable()->after('intake_summary');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['deferral_reason', 'deferral_notes', 'next_appointment_date']);
        });

        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->dropColumn('assessment_summary');
        });
    }
};
