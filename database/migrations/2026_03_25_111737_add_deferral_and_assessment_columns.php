<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add deferral detail columns to visits (skip if already added to the base migration)
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'deferral_reason')) {
                $table->string('deferral_reason')->nullable()->after('deferred_reason');
            }
            if (! Schema::hasColumn('visits', 'deferral_notes')) {
                $table->text('deferral_notes')->nullable()->after('deferral_reason');
            }
            if (! Schema::hasColumn('visits', 'next_appointment_date')) {
                $table->date('next_appointment_date')->nullable()->after('deferral_notes');
            }
        });

        // Add assessment_summary column to intake_assessments
        // (the original migration only has intake_summary; assessment_summary is used by the editor)
        Schema::table('intake_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('intake_assessments', 'assessment_summary')) {
                $table->text('assessment_summary')->nullable()->after('intake_summary');
            }
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
