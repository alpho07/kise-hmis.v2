<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_form_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('form_code', 50)->unique();
            $table->string('form_name', 200);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('form_type', ['initial_assessment', 'progress_note', 'discharge_summary', 'screening', 'custom']);
            $table->json('schema_definition');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->date('published_at')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('scoring_rules')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('form_code');
            $table->index('department_id');
            $table->index('form_type');
            $table->index('is_active');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_form_schemas');
    }
};