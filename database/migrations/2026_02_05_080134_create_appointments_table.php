<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            
            // Client & Visit
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Created when appointment is checked in');
            
            // Service Details
            $table->foreignId('department_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Provider assigned to appointment');
            
            // Scheduling
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->integer('duration')->default(30)->comment('Duration in minutes');
            $table->string('room_assigned')->nullable();
            
            // Appointment Type & Status
            $table->enum('appointment_type', ['new', 'follow_up', 'review', 'emergency'])->default('new');
            $table->enum('status', [
                'scheduled',
                'confirmed', 
                'checked_in',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
                'rescheduled'
            ])->default('scheduled');
            
            // Payment
            $table->enum('payment_status', ['pending', 'paid', 'waived'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            
            // Linked Records
            $table->foreignId('service_booking_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Created after payment');
            
            // Notes & Communication
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Timestamps
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['appointment_date', 'appointment_time']);
            $table->index(['client_id', 'appointment_date']);
            $table->index(['provider_id', 'appointment_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};