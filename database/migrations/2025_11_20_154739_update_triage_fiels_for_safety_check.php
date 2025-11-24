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
        Schema::table('triages', function (Blueprint $table) {
            // Triage number for tracking
            if (!Schema::hasColumn('triages', 'triage_number')) {
                $table->string('triage_number')->unique()->nullable()->after('id');
            }
            
            // Branch relationship
            if (!Schema::hasColumn('triages', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
            }
            
            // JSON fields for red flags and safeguarding
            if (!Schema::hasColumn('triages', 'red_flags')) {
                $table->json('red_flags')->nullable()->after('triage_notes');
            }
            if (!Schema::hasColumn('triages', 'has_red_flags')) {
                $table->boolean('has_red_flags')->default(false)->after('red_flags');
            }
            if (!Schema::hasColumn('triages', 'safeguarding_concerns')) {
                $table->json('safeguarding_concerns')->nullable()->after('has_red_flags');
            }
            if (!Schema::hasColumn('triages', 'has_safeguarding_concerns')) {
                $table->boolean('has_safeguarding_concerns')->default(false)->after('safeguarding_concerns');
            }
            
            // Risk assessment
            if (!Schema::hasColumn('triages', 'risk_flag')) {
                $table->enum('risk_flag', ['low', 'medium', 'high', 'crisis'])->default('low')->after('risk_level');
            }
            if (!Schema::hasColumn('triages', 'risk_score')) {
                $table->integer('risk_score')->nullable()->after('risk_flag');
            }
            
            // Clearance status (new field separate from triage_status)
            if (!Schema::hasColumn('triages', 'clearance_status')) {
                $table->enum('clearance_status', [
                    'cleared_for_service',
                    'medical_hold',
                    'crisis_protocol'
                ])->default('cleared_for_service')->after('triage_status');
            }
            
            // Next step routing
            if (!Schema::hasColumn('triages', 'next_step')) {
                $table->enum('next_step', [
                    'intake',
                    'payment',
                    'dispensary',
                    'crisis_team',
                    'service_point'
                ])->nullable()->after('clearance_status');
            }
            
            // Handover and notes
            if (!Schema::hasColumn('triages', 'handover_summary')) {
                $table->text('handover_summary')->nullable()->after('triage_notes');
            }
            if (!Schema::hasColumn('triages', 'pending_actions')) {
                $table->text('pending_actions')->nullable()->after('handover_summary');
            }
            
            // Crisis protocol tracking
            if (!Schema::hasColumn('triages', 'crisis_protocol_activated')) {
                $table->boolean('crisis_protocol_activated')->default(false)->after('pending_actions');
            }
            if (!Schema::hasColumn('triages', 'crisis_activated_at')) {
                $table->timestamp('crisis_activated_at')->nullable()->after('crisis_protocol_activated');
            }
            
            // Expiration tracking
            if (!Schema::hasColumn('triages', 'is_expired')) {
                $table->boolean('is_expired')->default(false)->after('cleared_at');
            }
            if (!Schema::hasColumn('triages', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_expired');
            }
        });

       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triages', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('triages_visit_id_clearance_status_index');
            $table->dropIndex('triages_client_id_risk_flag_index');
            $table->dropIndex('triages_is_expired_index');
            
            // Drop columns
            $table->dropColumn([
                'triage_number', 'branch_id', 'red_flags', 'has_red_flags',
                'safeguarding_concerns', 'has_safeguarding_concerns',
                'risk_flag', 'risk_score', 'clearance_status', 'next_step',
                'handover_summary', 'pending_actions', 'crisis_protocol_activated',
                'crisis_activated_at', 'is_expired', 'expires_at'
            ]);
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes($table);
        return array_key_exists($index, $indexes);
    }
};