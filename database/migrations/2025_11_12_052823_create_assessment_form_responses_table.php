<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_schema_id')->constrained('assessment_form_schemas')->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_session_id')->nullable()->constrained()->nullOnDelete();
            $table->json('response_data');
            $table->json('calculated_scores')->nullable();
            $table->text('summary')->nullable();
            $table->text('recommendations')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('filled_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('filled_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('form_schema_id');
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('service_session_id');
            $table->index('status');
            $table->index('filled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_form_responses');
    }
};