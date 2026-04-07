<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns required by QueueEntry model and ServiceQueueResource
 * that were present in the live MySQL database but not captured in prior migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('queue_entries', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('queue_entries', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('queue_entries', 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('queue_entries', 'service_provider_id')) {
                $table->foreignId('service_provider_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('queue_entries', 'serving_completed_at')) {
                $table->timestamp('serving_completed_at')->nullable();
            }
            // ServiceQueueResource orders by priority_level; original migration uses 'priority'
            if (!Schema::hasColumn('queue_entries', 'priority_level')) {
                $table->unsignedTinyInteger('priority_level')->nullable()->default(3);
            }
        });

        // Widen the 'status' ENUM to include values used by ServiceQueueResource
        // The original enum had: waiting, called, serving, completed, no_show, deferred
        // The resource uses: ready, in_service, completed — add the new values
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE queue_entries
                MODIFY COLUMN status
                ENUM('waiting','called','serving','completed','no_show','deferred','ready','in_service')
                NOT NULL DEFAULT 'waiting'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            foreach (['branch_id', 'service_id', 'department_id', 'service_provider_id', 'serving_completed_at', 'priority_level'] as $col) {
                if (Schema::hasColumn('queue_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
