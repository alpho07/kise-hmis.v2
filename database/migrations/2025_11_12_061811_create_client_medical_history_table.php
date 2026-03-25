<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_medical_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->json('previous_assessments')->nullable();
            $table->json('developmental_concerns')->nullable();
            $table->text('developmental_concerns_notes')->nullable();
            $table->json('assistive_devices_history')->nullable();
            $table->text('assistive_devices_notes')->nullable();
            $table->json('medical_conditions')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('surgical_history')->nullable();
            $table->text('immunization_status')->nullable();
            $table->text('family_medical_history')->nullable();
            $table->json('feeding_history')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_medical_history');
    }
};