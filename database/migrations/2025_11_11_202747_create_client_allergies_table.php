<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('allergy_type', ['drug', 'food', 'environmental', 'insect', 'latex', 'other']);
            $table->string('allergen_name', 200);
            $table->text('reaction')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe', 'life_threatening']);
            $table->date('diagnosed_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('client_id');
            $table->index('allergy_type');
            $table->index('severity');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_allergies');
    }
};