<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('visit_number', 50)->unique();
            $table->date('visit_date');
            $table->enum('visit_type', ['walk_in', 'appointment', 'follow_up', 'emergency'])->default('walk_in');
            $table->enum('current_stage', ['reception', 'triage', 'intake', 'billing', 'queue', 'cashier', 'service', 'completed', 'deferred'])->default('reception');
            $table->enum('status', ['open', 'checked_in', 'in_triage', 'in_intake', 'in_progress', 'awaiting_payment', 'in_queue', 'in_service', 'completed', 'deferred', 'cancelled'])->default('open');
            $table->text('chief_complaint')->nullable();
            $table->text('visit_purpose')->nullable();
            $table->string('referral_source')->nullable();
            $table->boolean('is_appointment')->default(false);
            $table->string('triage_path')->nullable();
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deferred_at')->nullable();
            $table->text('deferred_reason')->nullable();
            $table->string('deferral_reason')->nullable();
            $table->text('deferral_notes')->nullable();
            $table->date('next_appointment_date')->nullable();
            $table->boolean('service_available')->default(true);
            $table->string('unavailability_reason')->nullable();
            $table->text('unavailability_notes')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->boolean('requires_followup')->default(false);
            $table->text('purpose_notes')->nullable();
            $table->string('queue_number')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->string('payment_status')->nullable()->default('pending');
            $table->integer('total_services')->default(0);
            $table->integer('completed_services')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('branch_id');
            $table->index('client_id');
            $table->index('visit_date');
            $table->index('status');
            $table->index('current_stage');
            $table->index('visit_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};