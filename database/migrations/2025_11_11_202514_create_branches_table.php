<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * BRANCHES TABLE
     * ===============
     * Purpose: Multi-tenant branch management (Main, Satellite, Outreach)
     * 
     * Relationships:
     * - Has many: Users, Departments, Clients, Visits
     * 
     * Business Rules:
     * - Each branch has unique code (e.g., 'KS' for Kasarani)
     * - Branch code is used in UCI generation
     * - Only active branches accept new clients
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            // Primary Key
            $table->id();
            
            // Branch Identification
            $table->string('code', 10)->unique()->comment('Branch code for UCI (e.g., KS, SL, MK)');
            $table->string('name', 150)->comment('Branch full name (e.g., Kasarani Branch)');
            $table->enum('type', ['main', 'satellite', 'outreach'])->default('main')
                ->comment('Branch type: main (HQ), satellite (permanent), outreach (temporary)');
            
            // Contact Information
            $table->string('phone', 20)->nullable()->comment('Primary contact number');
            $table->string('email', 100)->nullable()->comment('Branch email address');
            $table->string('address', 255)->nullable()->comment('Physical address');
            $table->string('county', 50)->nullable()->comment('County location');
            $table->string('sub_county', 50)->nullable()->comment('Sub-county location');
            $table->decimal('latitude', 10, 8)->nullable()->comment('GPS latitude for mapping');
            $table->decimal('longitude', 11, 8)->nullable()->comment('GPS longitude for mapping');
            
            // Operational Details
            $table->boolean('is_active')->default(true)->comment('Branch operational status');
            $table->date('opened_at')->nullable()->comment('Branch opening date');
            $table->date('closed_at')->nullable()->comment('Branch closure date (if applicable)');
            $table->time('operating_hours_start')->default('08:00:00')->comment('Daily opening time');
            $table->time('operating_hours_end')->default('17:00:00')->comment('Daily closing time');
            $table->json('operating_days')->nullable()->comment('Operating days: [1=Monday, 2=Tuesday, etc.]');
            
            // Capacity & Configuration
            $table->integer('max_daily_clients')->default(100)->comment('Maximum clients per day');
            $table->integer('current_daily_clients')->default(0)->comment('Counter for today\'s clients');
            $table->date('last_client_reset_date')->nullable()->comment('Last date daily counter was reset');
            
            // Branch Manager
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Branch manager user ID');
            
            // Settings & Configuration
            $table->json('settings')->nullable()->comment('Branch-specific settings (JSON)');
            $table->text('notes')->nullable()->comment('Administrative notes');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete for historical data preservation');
            
            // Indexes for Performance
            $table->index('code', 'idx_branches_code');
            $table->index('type', 'idx_branches_type');
            $table->index('is_active', 'idx_branches_active');
            $table->index(['county', 'sub_county'], 'idx_branches_location');
            $table->index('created_at', 'idx_branches_created');
        });
        
        // Add table comment for documentation (MySQL only)
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement("ALTER TABLE branches COMMENT = 'Multi-tenant branch management for KISE centers'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};