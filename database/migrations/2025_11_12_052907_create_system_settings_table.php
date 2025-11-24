<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'array'])->default('string');
            $table->string('group', 50)->default('general');
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_editable')->default(true);
            $table->json('validation_rules')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('key');
            $table->index('group');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};