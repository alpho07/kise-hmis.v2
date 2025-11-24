<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('queue_name', 200);
            $table->string('queue_code', 50)->unique();
            $table->text('description')->nullable();
            $table->integer('max_capacity')->default(50);
            $table->integer('current_count')->default(0);
            $table->integer('sla_target_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->date('active_date')->useCurrent();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index('branch_id');
            $table->index('department_id');
            $table->index('queue_code');
            $table->index('is_active');
            $table->index('active_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};