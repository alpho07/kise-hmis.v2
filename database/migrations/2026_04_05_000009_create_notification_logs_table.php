<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_phone', 20);
            $table->enum('message_type', ['appointment_reminder', 'check_in_confirmation', 'disruption_alert', 'follow_up_booking']);
            $table->text('message_body');
            $table->unsignedBigInteger('appointment_id')->nullable(); // plain integer, no FK — survives appointment deletion
            $table->enum('status', ['mock', 'queued', 'sent', 'failed'])->default('mock');
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
