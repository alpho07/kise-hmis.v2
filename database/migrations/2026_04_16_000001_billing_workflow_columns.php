<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. intake_assessments — "interested in SHA/NCPWD" flag
        Schema::table('intake_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('intake_assessments', 'interested_in_sha_ncpwd')) {
                $table->boolean('interested_in_sha_ncpwd')->default(false)->after('expected_payment_method');
            }
        });

        // 2. invoices — billing approval tracking flags
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'billing_approved')) {
                $table->boolean('billing_approved')->default(false)->after('has_sponsor');
            }
            if (! Schema::hasColumn('invoices', 'requires_cashier')) {
                $table->boolean('requires_cashier')->default(false)->after('billing_approved');
            }
        });

        // 3. Add 'billing' to visits.current_stage enum if not present
        //    ('billing' is the stage name used in the codebase for billing admin queue)
        if (DB::getDriverName() === 'mysql') {
            // visits
            DB::statement("
                ALTER TABLE visits
                MODIFY COLUMN current_stage
                ENUM('reception','triage','intake','billing','billing_admin','queue','cashier','service','completed','deferred')
                NOT NULL DEFAULT 'reception'
            ");
            // visit_stages
            DB::statement("
                ALTER TABLE visit_stages
                MODIFY COLUMN stage
                ENUM('reception','triage','intake','billing','billing_admin','queue','cashier','service','completed','deferred')
                NOT NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('intake_assessments', 'interested_in_sha_ncpwd')) {
                $table->dropColumn('interested_in_sha_ncpwd');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'billing_approved')) {
                $table->dropColumn('billing_approved');
            }
            if (Schema::hasColumn('invoices', 'requires_cashier')) {
                $table->dropColumn('requires_cashier');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE visits
                MODIFY COLUMN current_stage
                ENUM('reception','triage','intake','billing','queue','cashier','service','completed','deferred')
                NOT NULL DEFAULT 'reception'
            ");
            DB::statement("
                ALTER TABLE visit_stages
                MODIFY COLUMN stage
                ENUM('reception','triage','intake','billing','queue','cashier','service','completed','deferred')
                NOT NULL
            ");
        }
    }
};
