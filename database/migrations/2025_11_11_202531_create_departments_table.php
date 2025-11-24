<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPARTMENTS TABLE
     * Service departments within branches (Physiotherapy, OT, Speech, etc.)
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            
            // Branch Relationship
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()
                ->comment('Branch this department belongs to');
            
            // Department Identification
            $table->string('code', 50)->comment('Department code (e.g., PHYS, OT, SLT)');
            $table->string('name', 150)->comment('Department name');
            $table->text('description')->nullable()->comment('Department description');
            
            // Queue Configuration
            $table->boolean('has_queue')->default(true)->comment('Does this department use a queue?');
            $table->integer('queue_capacity')->default(50)->comment('Maximum queue size');
            $table->integer('sla_target_minutes')->default(30)->comment('Service level agreement (minutes)');
            
            // Contact & Location
            $table->string('location', 200)->nullable()->comment('Room/Building location');
            $table->string('phone', 20)->nullable()->comment('Department phone');
            $table->string('email', 100)->nullable()->comment('Department email');
            
            // Department Head
            $table->foreignId('head_of_department_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Head of department user ID');
            
            // Status & Configuration
            $table->boolean('is_active')->default(true)->comment('Department operational status');
            $table->json('settings')->nullable()->comment('Department-specific settings');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique(['branch_id', 'code'], 'unique_dept_code_per_branch');
            $table->index('branch_id', 'idx_departments_branch');
            $table->index('is_active', 'idx_departments_active');
            $table->index('has_queue', 'idx_departments_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};