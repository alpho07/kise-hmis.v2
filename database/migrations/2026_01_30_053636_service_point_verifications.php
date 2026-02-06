<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_point_verifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users');
            
            // Verification Details
            $table->timestamp('verification_time');
            $table->enum('verification_status', [
                'verified', 'payment_missing', 'wrong_department',
                'service_unavailable', 'pending'
            ])->default('pending');
            
            // Verification Checks
            $table->boolean('payment_verified')->default(false);
            $table->boolean('booking_verified')->default(false);
            $table->boolean('routing_verified')->default(false);
            $table->boolean('service_available')->default(true);
            
            // Assignment
            $table->foreignId('provider_assigned_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room_assigned', 100)->nullable();
            
            // Unavailability Handling
            $table->text('unavailability_reason')->nullable();
            $table->timestamp('rescheduled_to')->nullable();
            $table->text('sensitization_notes')->nullable();
            
            // Sign-in
            $table->timestamp('signed_in_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            //$table->index('verification_time');
            //$table->index('verification_status');
            //$table->index(['department_id', 'verification_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_point_verifications');
    }
};