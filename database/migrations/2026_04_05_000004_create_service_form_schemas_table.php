<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_form_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_form_schema_id')->constrained('assessment_form_schemas')->cascadeOnDelete();
            $table->unique(['service_id', 'assessment_form_schema_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_form_schemas');
    }
};
