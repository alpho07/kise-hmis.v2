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
        Schema::create('medical_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            
            // Hold Details
            $table->text('reason')->comment('Why medical hold was placed');
            $table->enum('severity', ['minor', 'moderate', 'severe'])->default('moderate');
            $table->string('referred_to')->nullable()->comment('e.g., Dispensary, External Hospital');
            
            // Status Management
            $table->enum('status', ['active', 'cleared', 'escalated'])->default('active');
            
            // Clearance
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->text('clearance_notes')->nullable();
            
            // Audit
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['visit_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_holds');
    }
};