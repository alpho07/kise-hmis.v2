<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('queue_number');
            $table->integer('position')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['waiting', 'called', 'serving', 'completed', 'no_show', 'deferred'])->default('waiting');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('called_at')->nullable();
            $table->foreignId('called_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('serving_started_at')->nullable();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->integer('wait_time_minutes')->nullable();
            $table->integer('service_time_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('queue_id');
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('queue_number');
            $table->index('status');
            $table->index('priority');
            $table->index('joined_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};