<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add payment_details column to payments table for storing multiple payment methods
        Schema::table('payments', function (Blueprint $table) {
            $table->json('payment_details')->nullable()->after('payment_notes');
        });

        // Add discount column to invoice_items
        if (!Schema::hasColumn('invoice_items', 'discount')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->decimal('discount', 10, 2)->default(0)->after('quantity');
            });
        }

        // Create client_credit_accounts table
        Schema::create('client_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('account_number')->unique();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->decimal('available_credit', 10, 2)->default(0);
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'branch_id']);
        });

        // Create credit_transactions table
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_account_id')->constrained('client_credit_accounts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['charge', 'payment', 'adjustment', 'refund']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->text('description');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['credit_account_id', 'transaction_date']);
        });

        // Create insurance_claims table for batch claims
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('claim_number')->unique();
            $table->date('claim_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_invoices');
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['draft', 'submitted', 'approved', 'paid', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['insurance_provider_id', 'status']);
        });

        // Create insurance_claim_items table
        Schema::create('insurance_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('invoice_amount', 10, 2);
            $table->decimal('claimed_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['insurance_claim_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_items');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('client_credit_accounts');
        
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_details');
        });

        if (Schema::hasColumn('invoice_items', 'discount')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }
    }
};