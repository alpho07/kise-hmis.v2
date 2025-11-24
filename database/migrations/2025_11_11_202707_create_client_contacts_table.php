<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('contact_type', ['emergency', 'guardian', 'next_of_kin', 'caregiver'])->default('emergency');
            $table->string('full_name', 200);
            $table->string('relationship', 100)->nullable();
            $table->string('phone_primary', 20)->nullable();
            $table->string('phone_secondary', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('client_id');
            $table->index('contact_type');
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};