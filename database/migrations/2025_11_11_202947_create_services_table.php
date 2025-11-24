<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->boolean('sha_covered')->default(false);
            $table->decimal('sha_price', 10, 2)->nullable();
            $table->boolean('ncpwd_covered')->default(false);
            $table->decimal('ncpwd_price', 10, 2)->nullable();
            $table->boolean('requires_assessment')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->integer('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('subcategory', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('department_id');
            $table->index('is_active');
            $table->index('category');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};