<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_disabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_disability_known')->default(false);
            $table->json('disability_categories')->nullable();
            $table->enum('onset', ['congenital', 'acquired', 'unknown'])->nullable();
            $table->enum('level_of_functioning', ['mild', 'moderate', 'severe', 'profound'])->nullable();
            $table->json('assistive_technology')->nullable();
            $table->text('assistive_technology_notes')->nullable();
            $table->text('disability_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('client_id');
            $table->index('is_disability_known');
            $table->index('level_of_functioning');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_disabilities');
    }
};