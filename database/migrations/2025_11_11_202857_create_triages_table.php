<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->integer('heart_rate')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->integer('systolic_bp')->nullable();
            $table->integer('diastolic_bp')->nullable();
            $table->integer('oxygen_saturation')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('bmi_category', 50)->nullable();
            $table->integer('pain_scale')->nullable();
            $table->enum('consciousness_level', ['alert', 'verbal', 'pain', 'unresponsive'])->default('alert');
            $table->text('presenting_complaint')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->boolean('has_red_flags')->default(false);
            $table->enum('triage_status', ['cleared', 'medical_hold', 'crisis'])->default('cleared');
            $table->enum('routing_decision', ['proceed_to_intake', 'immediate_dispensary', 'refer_emergency', 'defer'])->default('proceed_to_intake');
            $table->text('triage_notes')->nullable();
            $table->foreignId('triaged_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('triaged_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('risk_level');
            $table->index('triage_status');
            $table->index('triaged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triages');
    }
};