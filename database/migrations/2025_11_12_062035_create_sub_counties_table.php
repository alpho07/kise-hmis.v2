<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_counties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('county_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('county_id');
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_counties');
    }
};