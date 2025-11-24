<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            //$table->foreignId('service_booking_id')->after('client_id')->constrained()->cascadeOnDelete();
            //$table->foreignId('service_id')->after('service_booking_id')->constrained()->cascadeOnDelete();
            //$table->foreignId('department_id')->after('service_id')->constrained()->cascadeOnDelete();
            $table->integer('estimated_duration')->nullable()->after('department_id')->comment('Duration in minutes');
            
            //$table->index('service_booking_id');
            $table->index('service_id');
            $table->index('department_id');
            $table->index(['queue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropForeign(['service_booking_id']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['department_id']);
            
            $table->dropIndex(['service_booking_id']);
            $table->dropIndex(['service_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['queue_id', 'status']);
            
            $table->dropColumn([
                'service_booking_id',
                'service_id',
                'department_id',
                'estimated_duration',
            ]);
        });
    }
};