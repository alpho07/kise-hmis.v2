<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. visits — reception notes
        if (!Schema::hasColumn('visits', 'reception_notes')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->text('reception_notes')->nullable()->after('purpose_notes');
            });
        }

        // 2. queue_entries — room assigned at service point
        if (!Schema::hasColumn('queue_entries', 'room_assigned')) {
            Schema::table('queue_entries', function (Blueprint $table) {
                $table->string('room_assigned', 100)->nullable()->after('service_provider_id');
            });
        }

        // 3. service_bookings — string priority for PaymentRoutingService
        if (!Schema::hasColumn('service_bookings', 'priority')) {
            Schema::table('service_bookings', function (Blueprint $table) {
                $table->string('priority', 20)->default('routine')->after('service_status');
            });
        }

        // 4. intake_assessments — payment routing & clinical fields
        Schema::table('intake_assessments', function (Blueprint $table) {
            if (!Schema::hasColumn('intake_assessments', 'expected_payment_method')) {
                $table->string('expected_payment_method', 50)->nullable()->after('priority_level');
            }
            if (!Schema::hasColumn('intake_assessments', 'presenting_problem')) {
                $table->text('presenting_problem')->nullable()->after('reason_for_visit');
            }
            if (!Schema::hasColumn('intake_assessments', 'history_present_illness')) {
                $table->text('history_present_illness')->nullable()->after('presenting_problem');
            }
            if (!Schema::hasColumn('intake_assessments', 'risk_level')) {
                $table->string('risk_level', 50)->nullable()->after('history_present_illness');
            }
            if (!Schema::hasColumn('intake_assessments', 'assessment_type')) {
                $table->string('assessment_type', 50)->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('intake_assessments', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('recommendations');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('visits', 'reception_notes')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropColumn('reception_notes');
            });
        }

        if (Schema::hasColumn('queue_entries', 'room_assigned')) {
            Schema::table('queue_entries', function (Blueprint $table) {
                $table->dropColumn('room_assigned');
            });
        }

        if (Schema::hasColumn('service_bookings', 'priority')) {
            Schema::table('service_bookings', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }

        Schema::table('intake_assessments', function (Blueprint $table) {
            $cols = ['expected_payment_method', 'presenting_problem', 'history_present_illness',
                     'risk_level', 'assessment_type', 'special_instructions'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('intake_assessments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
