<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_editable')->default(true);
            $table->enum('verification_mode', ['new_client', 'returning_client', 'old_new_client'])->default('new_client');
            $table->boolean('data_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('reason_for_visit')->nullable();
            $table->text('previous_interventions')->nullable();
            $table->text('current_concerns')->nullable();
            $table->text('family_history')->nullable();
            $table->text('developmental_history')->nullable();
            $table->text('educational_background')->nullable();
            $table->text('social_history')->nullable();
            $table->json('services_required')->nullable();
            $table->json('functional_screening_scores')->nullable();
            $table->text('intake_summary')->nullable();
            $table->text('recommendations')->nullable();
            $table->foreignId('assessed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assessed_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('is_editable');
            $table->index('data_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_assessments');
    }
};