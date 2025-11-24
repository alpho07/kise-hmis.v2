<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Form Schema Definitions
        Schema::create('assessment_form_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Comprehensive Intake Assessment"
            $table->string('slug')->unique(); // "intake-assessment"
            $table->string('version', 20)->default('1.0.0');
            $table->string('category', 100)->nullable(); // "clinical_assessment", "screening", "survey"
            $table->text('description')->nullable();
            
            // The actual form structure
            $table->json('schema');
            
            // Conditional logic rules
            $table->json('conditional_rules')->nullable();
            
            // Validation rules
            $table->json('validation_rules')->nullable();
            
            // Auto-referral configuration
            $table->json('auto_referrals')->nullable();
            
            // Metadata
            $table->integer('estimated_minutes')->nullable();
            $table->boolean('allow_draft')->default(true);
            $table->boolean('allow_partial_submission')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_published')->default(false);
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['category', 'is_active']);
            $table->index(['slug', 'is_active']);
        });

        // Form Responses
        Schema::create('assessment_form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_schema_id')->constrained('assessment_form_schemas')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            
            // All form responses stored as JSON
            $table->json('response_data');
            
            // Metadata about the response
            $table->json('metadata')->nullable()->comment('User agent, IP, duration, etc.');
            
            // Status tracking
            $table->enum('status', ['draft', 'in_progress', 'completed', 'submitted', 'archived'])
                ->default('draft')
                ->index();
            
            // Completion tracking
            $table->integer('completion_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            
            // User tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['form_schema_id', 'status']);
            $table->index(['client_id', 'form_schema_id']);
            $table->index(['visit_id', 'status']);
            $table->index('created_at');
        });

        // Form Versions (for tracking schema changes)
        Schema::create('assessment_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_schema_id')->constrained('assessment_form_schemas')->cascadeOnDelete();
            $table->string('version', 20);
            $table->json('schema_snapshot'); // Full snapshot of the schema at this version
            $table->text('change_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index(['form_schema_id', 'version']);
        });

        // Auto-generated Referrals (from form responses)
        Schema::create('assessment_auto_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_response_id')->constrained('assessment_form_responses')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('visit_id')->nullable()->constrained();
            
            $table->string('service_point'); // "Occupational Therapy", "Audiology", etc.
            $table->string('department')->nullable();
            $table->enum('priority', ['low', 'routine', 'high', 'urgent'])->default('routine');
            $table->text('reason');
            $table->json('trigger_data')->nullable()->comment('The field/value that triggered this');
            
            $table->enum('status', ['pending', 'acknowledged', 'scheduled', 'completed', 'cancelled'])
                ->default('pending')
                ->index();
            
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            
            $table->timestamps();
            
            $table->index(['service_point', 'status']);
            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_auto_referrals');
        Schema::dropIfExists('assessment_form_versions');
        Schema::dropIfExists('assessment_form_responses');
        Schema::dropIfExists('assessment_form_schemas');
    }
};