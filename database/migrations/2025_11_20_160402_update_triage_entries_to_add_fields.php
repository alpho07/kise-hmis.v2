<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            // Priority based on triage
            if (!Schema::hasColumn('queue_entries', 'priority_level')) {
                $table->enum('priority_level', ['critical', 'urgent', 'high', 'normal', 'low'])
                    ->default('normal')
                    ->after('status')
                    ->comment('Set from triage risk assessment');
            }
            
            // Timestamps for queue tracking
            if (!Schema::hasColumn('queue_entries', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable()->after('priority_level');
            }
            if (!Schema::hasColumn('queue_entries', 'waiting_started_at')) {
                $table->timestamp('waiting_started_at')->nullable()->after('arrived_at');
            }
            if (!Schema::hasColumn('queue_entries', 'service_started_at')) {
                $table->timestamp('service_started_at')->nullable()->after('waiting_started_at');
            }
            if (!Schema::hasColumn('queue_entries', 'service_completed_at')) {
                $table->timestamp('service_completed_at')->nullable()->after('service_started_at');
            }
            
            // Wait time calculation support
            if (!Schema::hasColumn('queue_entries', 'wait_time_minutes')) {
                $table->integer('wait_time_minutes')->nullable()->after('service_completed_at');
            }
        });

        // Add index for priority ordering
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->index(['arrived_at', 'priority_level', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropIndex(['queue_date', 'priority_level', 'position']);
            
            $table->dropColumn([
                'priority_level',
                'arrived_at',
                'waiting_started_at',
                'service_started_at',
                'service_completed_at',
                'wait_time_minutes',
            ]);
        });
    }
};