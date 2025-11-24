<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLIENT ADDRESSES TABLE
     * Physical and postal addresses for clients
     */
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            
            // Client Relationship
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()
                ->comment('Client this address belongs to');
            
            // Address Type
            $table->enum('type', ['physical', 'postal', 'temporary'])->default('physical')
                ->comment('Address type');
            $table->boolean('is_primary')->default(false)->comment('Primary address flag');
            
            // Address Details
            $table->text('address_line_1')->nullable()->comment('Street address line 1');
            $table->text('address_line_2')->nullable()->comment('Street address line 2');
            $table->string('city', 100)->nullable()->comment('City/town');
            $table->string('county', 50)->nullable()->comment('County');
            $table->string('postal_code', 20)->nullable()->comment('Postal code');
            $table->string('country', 50)->default('Kenya')->comment('Country');
            
            // GPS Coordinates
            $table->decimal('latitude', 10, 8)->nullable()->comment('GPS latitude');
            $table->decimal('longitude', 11, 8)->nullable()->comment('GPS longitude');
            
            // Metadata
            $table->text('notes')->nullable()->comment('Address notes/directions');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('client_id', 'idx_addresses_client');
            $table->index('type', 'idx_addresses_type');
            $table->index('is_primary', 'idx_addresses_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};