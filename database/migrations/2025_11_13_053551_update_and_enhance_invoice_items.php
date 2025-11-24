<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            //$table->foreignId('service_booking_id')->after('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->after('service_id')->constrained()->cascadeOnDelete();
            
            //$table->index('service_booking_id');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['service_booking_id']);
            $table->dropForeign(['department_id']);
            
            $table->dropIndex(['service_booking_id']);
            $table->dropIndex(['department_id']);
            
            $table->dropColumn(['service_booking_id', 'department_id']);
        });
    }
};