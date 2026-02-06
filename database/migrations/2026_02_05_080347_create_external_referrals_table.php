<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_referrals', function (Blueprint $table) {
            $table->id();
            
            // Visit & Client
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            
            // Referring Provider
            $table->foreignId('referring_provider_id')->constrained('users');
            $table->foreignId('from_department_id')->constrained('departments');
            
            // Destination Facility
            $table->string('facility_name');
            $table->enum('facility_type', [
                'hospital',
                'specialist_clinic',
                'diagnostic_center',
                'rehabilitation_center',
                'other'
            ]);
            $table->string('department_specialty')->nullable()
                ->comment('e.g., Pediatric Ophthalmology, Neurology');
            $table->string('facility_contact')->nullable();
            $table->string('facility_email')->nullable();
            
            // Referral Details
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', [
                'sent',
                'appointment_scheduled',
                'attended',
                'completed',
                'cancelled',
                'lost_to_follow_up'
            ])->default('sent');
            
            // Clinical Information
            $table->text('reason')
                ->comment('Main reason for referral');
            $table->text('clinical_summary')
                ->comment('Brief clinical summary');
            $table->text('investigations_done')->nullable()
                ->comment('Tests/assessments completed at KISE');
            $table->text('current_management')->nullable()
                ->comment('Current treatment/interventions');
            $table->text('specific_request')->nullable()
                ->comment('What is specifically requested from specialist');
            
            // Contact Information
            $table->string('preferred_contact')
                ->comment('Client/guardian preferred contact');
            $table->string('alternative_contact')->nullable();
            
            // Documents
            $table->string('referral_letter_path')->nullable()
                ->comment('Path to generated referral letter PDF');
            $table->json('supporting_documents')->nullable()
                ->comment('Array of document paths');
            
            // Follow-up
            $table->date('appointment_date')->nullable();
            $table->time('appointment_time')->nullable();
            $table->text('feedback')->nullable()
                ->comment('Feedback from receiving facility');
            $table->json('feedback_documents')->nullable()
                ->comment('Reports/letters from facility');
            
            // Timestamps
            $table->timestamp('referred_at')->useCurrent();
            $table->timestamp('appointment_confirmed_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('feedback_received_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['client_id', 'status']);
            $table->index('status');
            $table->index('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_referrals');
    }
};