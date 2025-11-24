<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $table->string('membership_number')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('principal_member_name')->nullable();
            $table->string('principal_member_id')->nullable();
            $table->enum('relationship_to_principal', ['self', 'spouse', 'child', 'parent', 'other'])->default('self');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->json('coverage_details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'is_active']);
            $table->index(['insurance_provider_id', 'membership_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_insurances');
    }
};