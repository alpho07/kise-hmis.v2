<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crisis_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 50)->unique();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('incident_type', ['behavioral', 'medical', 'safety', 'environmental', 'other']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamp('occurred_at');
            $table->string('location', 200);
            $table->text('description');
            $table->text('trigger_factors')->nullable();
            $table->text('immediate_actions_taken');
            $table->text('interventions_applied')->nullable();
            $table->text('client_response')->nullable();
            $table->boolean('injury_occurred')->default(false);
            $table->text('injury_details')->nullable();
            $table->boolean('emergency_services_called')->default(false);
            $table->text('emergency_services_details')->nullable();
            $table->boolean('family_notified')->default(false);
            $table->timestamp('family_notified_at')->nullable();
            $table->text('follow_up_required')->nullable();
            $table->enum('status', ['active', 'under_review', 'resolved', 'escalated'])->default('active');
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reported_at')->useCurrent();
            $table->json('witnesses')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('incident_number');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('incident_type');
            $table->index('severity');
            $table->index('status');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crisis_incidents');
    }
};