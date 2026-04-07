<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'total_sponsor_amount')) {
                $table->decimal('total_sponsor_amount', 10, 2)->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('invoices', 'total_client_amount')) {
                $table->decimal('total_client_amount', 10, 2)->default(0)->after('total_sponsor_amount');
            }
            if (! Schema::hasColumn('invoices', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('paid_amount');
            }
            if (! Schema::hasColumn('invoices', 'has_sponsor')) {
                $table->boolean('has_sponsor')->default(false)->after('amount_paid');
            }
            if (! Schema::hasColumn('invoices', 'client_payment_status')) {
                $table->string('client_payment_status', 50)->nullable()->after('has_sponsor');
            }
            if (! Schema::hasColumn('invoices', 'sponsor_claim_status')) {
                $table->string('sponsor_claim_status', 50)->nullable()->after('client_payment_status');
            }
            if (! Schema::hasColumn('invoices', 'payment_notes')) {
                $table->text('payment_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumnIfExists([
                'total_sponsor_amount',
                'total_client_amount',
                'amount_paid',
                'has_sponsor',
                'client_payment_status',
                'sponsor_claim_status',
                'payment_notes',
            ]);
        });
    }
};
