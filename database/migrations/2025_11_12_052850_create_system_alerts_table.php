<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('alert_type', ['info', 'warning', 'error', 'critical', 'success']);
            $table->string('title', 200);
            $table->text('message');
            $table->enum('category', ['system', 'user', 'client', 'billing', 'queue', 'service', 'security', 'other'])->default('system');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->string('action_label', 100)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('alert_type');
            $table->index('category');
            $table->index('user_id');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('is_read');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};