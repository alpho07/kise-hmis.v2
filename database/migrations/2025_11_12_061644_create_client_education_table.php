<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('education_level', ['none', 'ecd', 'primary', 'secondary', 'tertiary', 'vocational'])->nullable();
            $table->enum('school_type', ['regular', 'special', 'integrated', 'homeschool'])->nullable();
            $table->string('school_name', 200)->nullable();
            $table->string('grade_level', 50)->nullable();
            $table->boolean('currently_enrolled')->default(false);
            $table->enum('employment_status', ['unemployed', 'employed', 'self_employed', 'student', 'retired'])->nullable();
            $table->string('occupation_type', 100)->nullable();
            $table->string('employer_name', 200)->nullable();
            $table->text('education_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('client_id');
            $table->index('education_level');
            $table->index('school_type');
            $table->index('employment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_education');
    }
};