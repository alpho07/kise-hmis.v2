<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. VISITS TABLE - Add workflow fields
       /* Schema::table('visits', function (Blueprint $table) {
            $table->text('visit_purpose')->nullable()->after('visit_type');
            $table->enum('referral_source', ['walk_in', 'referral', 'appointment', 'outreach'])->nullable()->after('visit_purpose');
            $table->boolean('is_appointment')->default(false)->after('referral_source');
            $table->enum('triage_path', ['standard', 'medical_veto', 'crisis'])->nullable()->after('is_appointment');
        });

        // 2. CLIENTS TABLE - Add last visit tracking
        Schema::table('clients', function (Blueprint $table) {
            $table->date('last_visit_date')->nullable()->after('created_at');
        });

        // 3. TRIAGES TABLE - Add clearance tracking
        Schema::table('triages', function (Blueprint $table) {
            $table->foreignId('cleared_by')->nullable()->after('triaged_by')->constrained('users');
            $table->timestamp('cleared_at')->nullable()->after('cleared_by');
        });

        // 4. INTAKE ASSESSMENTS TABLE - Add service recommendations
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->json('service_recommendations')->nullable()->after('recommendations');
            $table->json('referral_categories')->nullable()->after('service_recommendations');
            $table->integer('priority_level')->default(3)->after('referral_categories')->comment('1=high, 5=low');
        });

        // 5. INVOICES TABLE - Add payment administrator
        Schema::table('invoices', function (Blueprint $table) {

            $table->foreignId('payment_administrator_id')->nullable()->after('generated_by')->constrained('users');
            $table->text('payment_notes')->nullable()->after('notes');
        });

        // 6. QUEUE ENTRIES TABLE - Add no-show and caller tracking
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->boolean('no_show')->default(false)->after('served_by');
            $table->foreignId('called_by')->nullable()->after('no_show')->constrained('users');
        });
   
        // 7. SERVICE SESSIONS TABLE - Add service point workflow
        Schema::table('service_sessions', function (Blueprint $table) {
            $table->foreignId('signed_in_by')->nullable()->after('provider_id')->constrained('users')->comment('Customer care staff who signed client in');
            $table->json('baseline_assessment_data')->nullable()->after('duration_minutes');
            $table->text('intervention_plan')->nullable()->after('baseline_assessment_data');
        });

        // 8. QUEUES TABLE - Add display name
        Schema::table('queues', function (Blueprint $table) {
            $table->string('queue_display_name')->nullable()->after('queue_name');
        });

        // 9. PAYMENTS TABLE - Add M-Pesa receipt
        Schema::table('payments', function (Blueprint $table) {
            $table->string('mpesa_receipt_number')->nullable()->after('transaction_id');
        });
*/
        // 10. INVOICES TABLE - Change payment_method to support insurance
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('insurance_provider_id')->nullable()->after('payment_pathway')->constrained('insurance_providers');
        });

        // 11. INVOICE ITEMS TABLE - Add insurance details
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('insurance_provider_id')->nullable()->after('department_id')->constrained('insurance_providers');
            $table->decimal('insurance_covered_amount', 10, 2)->default(0)->after('unit_price');
            $table->decimal('client_copay_amount', 10, 2)->default(0)->after('insurance_covered_amount');
        });

        // 12. SERVICE BOOKINGS TABLE - Add insurance
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->foreignId('insurance_provider_id')->nullable()->after('assigned_provider_id')->constrained('insurance_providers');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['visit_purpose', 'referral_source', 'is_appointment', 'triage_path']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('last_visit_date');
        });

        Schema::table('triages', function (Blueprint $table) {
            $table->dropForeign(['cleared_by']);
            $table->dropColumn(['cleared_by', 'cleared_at']);
        });

        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->dropColumn(['service_recommendations', 'referral_categories', 'priority_level']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_administrator_id']);
            $table->dropForeign(['insurance_provider_id']);
            $table->dropColumn(['payment_administrator_id', 'payment_notes', 'insurance_provider_id']);
        });

        Schema::table('queue_entries', function (Blueprint $table) {
           $table->dropForeign(['called_by']);
            $table->dropColumn(['no_show', 'called_by']);
        });

        Schema::table('service_sessions', function (Blueprint $table) {
            $table->dropForeign(['signed_in_by']);
            $table->dropColumn(['signed_in_by', 'baseline_assessment_data', 'intervention_plan']);
        });

        Schema::table('queues', function (Blueprint $table) {
            $table->dropColumn('queue_display_name');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('mpesa_receipt_number');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['insurance_provider_id']);
            $table->dropColumn(['insurance_provider_id', 'insurance_covered_amount', 'client_copay_amount']);
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropForeign(['insurance_provider_id']);
            $table->dropColumn('insurance_provider_id');
        });
    }
};