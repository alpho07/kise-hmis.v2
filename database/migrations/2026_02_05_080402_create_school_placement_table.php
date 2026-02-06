<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schools Table
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            
            // School Information
            $table->string('name');
            $table->enum('school_type', ['special', 'regular', 'integrated'])->default('special');
            $table->string('registration_number')->nullable()->unique();
            
            // Location
            $table->foreignId('county_id')->constrained();
            $table->foreignId('sub_county_id')->nullable()->constrained();
            $table->string('location')->nullable();
            $table->text('physical_address')->nullable();
            
            // Contact Information
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // School Details
            $table->json('specializations')->nullable()
                ->comment('Array of disabilities supported: vision_impairment, hearing_impairment, etc.');
            $table->boolean('boarding')->default(false);
            $table->string('grades_offered')->nullable()
                ->comment('e.g., ECD-Grade 8, Form 1-4');
            $table->integer('capacity')->nullable();
            
            // Facilities & Services
            $table->json('facilities')->nullable()
                ->comment('Array of facilities: library, therapy_room, etc.');
            $table->json('support_services')->nullable()
                ->comment('Array of services: braille, sign_language, etc.');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'pending_approval'])->default('active');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('school_type');
            $table->index('county_id');
            $table->index('status');
        });

        // School Placements Table
        Schema::create('school_placements', function (Blueprint $table) {
            $table->id();
            
            // Client & School
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained();
            
            // Placement Details
            $table->enum('placement_type', [
                'special_school',
                'integrated_program',
                'inclusive_education',
                'home_schooling'
            ])->default('special_school');
            
            $table->string('program')->nullable()
                ->comment('Specific program: Vision Impairment Support, etc.');
            $table->string('grade_level');
            $table->date('admission_date');
            $table->date('expected_completion_date')->nullable();
            
            // Status
            $table->enum('status', [
                'pending',
                'active',
                'completed',
                'transferred',
                'discontinued',
                'graduated'
            ])->default('pending');
            
            // Support & Performance
            $table->json('support_services')->nullable()
                ->comment('Array of support services provided');
            $table->string('academic_performance')->nullable()
                ->comment('Latest academic performance: excellent, good, fair, poor');
            $table->string('social_performance')->nullable()
                ->comment('Social integration: excellent, good, fair, poor');
            $table->text('special_needs')->nullable()
                ->comment('Specific accommodations/modifications needed');
            
            // Reviews
            $table->date('last_review_date')->nullable();
            $table->text('review_notes')->nullable();
            
            // Placement Process
            $table->foreignId('placement_officer_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('KISE officer who facilitated placement');
            $table->text('assessment_summary')->nullable()
                ->comment('Summary of assessments that led to placement');
            $table->string('placement_letter_path')->nullable()
                ->comment('Path to placement letter PDF');
            
            // Termination/Transfer
            $table->date('end_date')->nullable();
            $table->enum('exit_reason', [
                'completed',
                'transferred',
                'discontinued',
                'graduated',
                'other'
            ])->nullable();
            $table->text('exit_notes')->nullable();
            
            // Contact
            $table->string('school_contact_person')->nullable();
            $table->string('school_contact_phone')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['client_id', 'status']);
            $table->index('school_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_placements');
        Schema::dropIfExists('schools');
    }
};