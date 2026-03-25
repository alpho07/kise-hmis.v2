<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functional_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->integer('mobility_score')->nullable();
            $table->integer('self_care_score')->nullable();
            $table->integer('communication_score')->nullable();
            $table->integer('cognition_score')->nullable();
            $table->integer('social_interaction_score')->nullable();
            $table->integer('emotional_regulation_score')->nullable();
            $table->integer('sensory_processing_score')->nullable();
            $table->integer('activities_daily_living_score')->nullable();
            $table->integer('total_score')->nullable();
            $table->text('mobility_notes')->nullable();
            $table->text('self_care_notes')->nullable();
            $table->text('communication_notes')->nullable();
            $table->text('cognition_notes')->nullable();
            $table->text('social_interaction_notes')->nullable();
            $table->text('emotional_regulation_notes')->nullable();
            $table->text('sensory_processing_notes')->nullable();
            $table->text('activities_daily_living_notes')->nullable();
            $table->text('overall_summary')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('intake_assessment_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('functional_screenings');
    }
};