<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('target_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('sync_type', ['outreach_to_main', 'main_to_outreach', 'branch_to_branch', 'backup']);
            $table->enum('data_type', ['clients', 'visits', 'services', 'invoices', 'all']);
            $table->timestamp('sync_started_at');
            $table->timestamp('sync_completed_at')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'partial'])->default('pending');
            $table->integer('records_total')->default(0);
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('sync_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('source_branch_id');
            $table->index('target_branch_id');
            $table->index('sync_type');
            $table->index('status');
            $table->index('sync_started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sync_logs');
    }
};