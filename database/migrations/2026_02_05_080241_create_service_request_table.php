<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            
            // Visit & Client
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            
            // Request Origin
            $table->foreignId('requested_by')->constrained('users')
                ->comment('Provider who requested the service');
            $table->foreignId('requesting_department_id')->constrained('departments')
                ->comment('Department where request originated');
            
            // Service Details
            $table->foreignId('service_id')->constrained();
            $table->foreignId('service_department_id')->constrained('departments')
                ->comment('Department that will provide the service');
            
            // Request Type & Priority
            $table->enum('request_type', [
                'additional_service',
                'lab',
                'imaging',
                'internal_referral',
                'consultation',
                'follow_up'
            ])->default('additional_service');
            
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            
            // Status Tracking
            $table->enum('status', [
                'pending_payment',
                'paid',
                'in_queue',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('pending_payment');
            
            // Clinical Information
            $table->text('clinical_notes')->nullable()
                ->comment('Reason for request, clinical findings');
            $table->text('clinical_findings')->nullable();
            $table->text('recommendations')->nullable();
            
            // Financial
            $table->decimal('cost', 10, 2);
            $table->string('payment_method')->nullable();
            $table->decimal('client_amount', 10, 2)->nullable();
            $table->decimal('sponsor_amount', 10, 2)->nullable();
            
            // Linked Records (created after payment)
            $table->foreignId('service_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['visit_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['service_department_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};