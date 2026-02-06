<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_referrals', function (Blueprint $table) {
            $table->id();
            
            // Visit & Client
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            
            // Referral Details
            $table->foreignId('from_department_id')->constrained('departments');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->foreignId('referring_provider_id')->constrained('users')
                ->comment('Provider making the referral');
            $table->foreignId('accepting_provider_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Provider who accepted the referral');
            
            // Service
            $table->foreignId('service_id')->constrained();
            
            // Priority & Status
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            $table->enum('status', [
                'pending',
                'accepted',
                'in_progress',
                'completed',
                'rejected',
                'cancelled'
            ])->default('pending');
            
            // Clinical Information
            $table->text('clinical_reason')
                ->comment('Why referral is needed');
            $table->text('findings')->nullable()
                ->comment('Relevant clinical findings');
            $table->text('investigations_done')->nullable()
                ->comment('Tests/assessments already completed');
            $table->text('recommendations')->nullable()
                ->comment('Specific recommendations for receiving department');
            
            // Response from Receiving Department
            $table->text('acceptance_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('outcome')->nullable()
                ->comment('Final outcome after service delivery');
            
            // Linked Records
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Created when referral is made');
            $table->foreignId('service_booking_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Created after payment');
            
            // Timestamps
            $table->timestamp('referred_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['client_id', 'status']);
            $table->index(['to_department_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_referrals');
    }
};