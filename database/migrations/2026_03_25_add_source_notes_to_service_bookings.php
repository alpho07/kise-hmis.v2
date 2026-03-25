<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('service_bookings', 'source')) {
                $table->string('source')->nullable()->after('booked_by');
            }
            if (! Schema::hasColumn('service_bookings', 'notes')) {
                $table->text('notes')->nullable()->after('source');
            }
            if (! Schema::hasColumn('service_bookings', 'booking_type')) {
                $table->string('booking_type')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('service_bookings', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('booking_type');
            }
            if (! Schema::hasColumn('service_bookings', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('booking_date');
            }
            if (! Schema::hasColumn('service_bookings', 'service_status')) {
                $table->string('service_status')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('service_bookings', 'priority_level')) {
                $table->integer('priority_level')->nullable()->after('service_status');
            }
            if (! Schema::hasColumn('service_bookings', 'priority')) {
                $table->string('priority')->nullable()->after('priority_level');
            }
            if (! Schema::hasColumn('service_bookings', 'invoice_item_id')) {
                $table->unsignedBigInteger('invoice_item_id')->nullable()->after('priority');
                $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('service_bookings', 'invoice_item_id')) {
                $table->dropForeign(['invoice_item_id']);
                $table->dropColumn('invoice_item_id');
            }
            // Note: source, notes, booking_type, booking_date, payment_status,
            // service_status, priority_level, priority were added by earlier migrations
            // and are not dropped here to avoid data loss.
        });
    }
};
