<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_red_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_id')->constrained()->cascadeOnDelete();
            $table->enum('flag_category', ['respiratory', 'cardiovascular', 'neurological', 'trauma', 'behavioral', 'other']);
            $table->string('flag_name', 200);
            $table->text('description')->nullable();
            $table->enum('severity', ['warning', 'critical', 'emergency'])->default('warning');
            $table->boolean('requires_immediate_attention')->default(false);
            $table->text('action_taken')->nullable();
            $table->timestamps();
            
            $table->index('triage_id');
            $table->index('flag_category');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_red_flags');
    }
};