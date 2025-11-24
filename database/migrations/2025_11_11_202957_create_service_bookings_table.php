<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('booked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamps();
            
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('service_id');
            $table->index('department_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bookings');
    }
};