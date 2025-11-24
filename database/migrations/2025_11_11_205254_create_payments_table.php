<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'mpesa', 'bank_transfer', 'cheque', 'card', 'sha', 'ncpwd', 'nhif', 'other']);
            $table->decimal('amount', 10, 2);
            $table->string('transaction_reference', 100)->nullable();
            $table->string('payer_name', 200)->nullable();
            $table->string('payer_phone', 20)->nullable();
            $table->text('payment_notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refund_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('payment_number');
            $table->index('invoice_id');
            $table->index('visit_id');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('payment_method');
            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};