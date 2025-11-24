<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_insurance_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $table->decimal('covered_amount', 10, 2);
            $table->decimal('client_copay', 10, 2)->default(0);
            $table->decimal('coverage_percentage', 5, 2)->default(100.00);
            $table->boolean('is_fully_covered')->default(true);
            $table->boolean('requires_preauthorization')->default(false);
            $table->string('preauthorization_code')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            //$table->unique(['service_id', 'insurance_provider_id'], 'service_insurance_unique');
            //$table->index(['service_id', 'insurance_provider_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_insurance_prices');
    }
};