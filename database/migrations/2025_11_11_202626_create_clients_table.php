<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLIENTS TABLE
     * Core client information with UCI (Unique Client Identifier)
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            
            // Branch Relationship (Multi-Tenancy)
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()
                ->comment('Branch where client was registered');
            
            // Unique Client Identifier (UCI)
            $table->string('uci', 50)->unique()->comment('Unique Client Identifier (e.g., KS-2024-00001)');
            
            // Personal Information
            $table->string('first_name', 100)->comment('Client first name');
            $table->string('middle_name', 100)->nullable()->comment('Client middle name');
            $table->string('last_name', 100)->comment('Client last name');
            $table->date('date_of_birth')->comment('Date of birth');
            $table->enum('gender', ['male', 'female', 'intersex', 'prefer_not_to_say'])
                ->comment('Client gender');
            
            // Contact Information
            $table->string('phone_primary', 20)->nullable()->comment('Primary phone number');
            $table->string('phone_secondary', 20)->nullable()->comment('Secondary phone number');
            $table->string('email', 100)->nullable()->comment('Email address');
            
            // Identification Documents
            $table->string('national_id', 20)->nullable()->unique()->comment('National ID number');
            $table->string('birth_certificate_number', 50)->nullable()->comment('Birth certificate number');
            $table->string('passport_number', 50)->nullable()->comment('Passport number');
            $table->string('nhif_number', 50)->nullable()->comment('NHIF number');
            $table->string('sha_number', 50)->nullable()->comment('SHA (Social Health Authority) number');
            $table->string('ncpwd_number', 50)->nullable()->comment('NCPWD registration number');
            
            // Guardian/Parent Information (for minors)
            $table->string('guardian_name', 200)->nullable()->comment('Guardian/parent full name');
            $table->string('guardian_relationship', 50)->nullable()->comment('Relationship to client');
            $table->string('guardian_phone', 20)->nullable()->comment('Guardian phone number');
            $table->string('guardian_national_id', 20)->nullable()->comment('Guardian national ID');
            
            // Location Information
            $table->string('county', 50)->nullable()->comment('County of residence');
            $table->string('sub_county', 50)->nullable()->comment('Sub-county');
            $table->string('ward', 50)->nullable()->comment('Ward');
            $table->string('village', 100)->nullable()->comment('Village/estate');
            
            // Medical Information
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'])
                ->default('unknown')->comment('Blood group');
            $table->text('medical_notes')->nullable()->comment('Important medical notes');
            
            // Registration Information
            $table->enum('client_type', ['new', 'returning', 'old_new'])
                ->default('new')->comment('Client registration type');
            $table->date('first_visit_date')->nullable()->comment('Date of first visit');
            $table->string('referral_source', 100)->nullable()->comment('How client found KISE');
            
            // Status
            $table->boolean('is_active')->default(true)->comment('Client account active status');
            $table->text('deactivation_reason')->nullable()->comment('Reason if deactivated');
            
            // Profile Photo
            $table->string('photo_path', 255)->nullable()->comment('Profile photo storage path');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional flexible data');
            $table->text('notes')->nullable()->comment('Administrative notes');
            
            // Audit
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete()
                ->comment('User who registered this client');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for Performance
            $table->index('branch_id', 'idx_clients_branch');
            $table->index('uci', 'idx_clients_uci');
            $table->index(['first_name', 'last_name'], 'idx_clients_name');
            $table->index('date_of_birth', 'idx_clients_dob');
            $table->index('phone_primary', 'idx_clients_phone');
            $table->index('national_id', 'idx_clients_national_id');
            $table->index('client_type', 'idx_clients_type');
            $table->index('is_active', 'idx_clients_active');
            $table->index('created_at', 'idx_clients_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};