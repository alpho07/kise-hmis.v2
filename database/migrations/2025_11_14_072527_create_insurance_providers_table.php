<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->enum('type', ['public', 'private', 'government'])->default('private');
            $table->text('description')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('claim_submission_method')->nullable(); // online, manual, email
            $table->string('claim_email')->nullable();
            $table->string('claim_portal_url')->nullable();
            $table->decimal('default_coverage_percentage', 5, 2)->default(100.00);
            $table->json('coverage_limits')->nullable(); // per service type
            $table->json('excluded_services')->nullable(); // services not covered
            $table->integer('claim_processing_days')->nullable();
            $table->boolean('requires_preauthorization')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_providers');
    }
};