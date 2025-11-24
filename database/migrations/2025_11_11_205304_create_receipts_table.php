<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'mpesa', 'bank_transfer', 'cheque', 'card', 'sha', 'ncpwd', 'nhif', 'other']);
            $table->string('receipt_type', 50)->default('payment');
            $table->text('description')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('receipt_number');
            $table->index('payment_id');
            $table->index('invoice_id');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};