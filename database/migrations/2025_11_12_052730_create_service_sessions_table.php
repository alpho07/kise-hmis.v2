<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->string('session_number', 50);
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('session_goals')->nullable();
            $table->text('activities_performed')->nullable();
            $table->text('client_response')->nullable();
            $table->text('observations')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('homework_assigned')->nullable();
            $table->enum('attendance', ['present', 'absent', 'late'])->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('service_booking_id');
            $table->index('provider_id');
            $table->index('session_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_sessions');
    }
};