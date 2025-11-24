<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->integer('queue_number');
            $table->integer('position')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent']);
            $table->enum('final_status', ['completed', 'no_show', 'deferred']);
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('serving_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('wait_time_minutes')->nullable();
            $table->integer('service_time_minutes')->nullable();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
            
            $table->index('queue_id');
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('final_status');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_history');
    }
};