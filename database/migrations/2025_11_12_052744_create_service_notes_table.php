<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->enum('note_type', ['progress', 'clinical', 'administrative', 'safety', 'other'])->default('progress');
            $table->text('note_content');
            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('service_session_id');
            $table->index('client_id');
            $table->index('provider_id');
            $table->index('note_type');
            $table->index('is_confidential');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_notes');
    }
};