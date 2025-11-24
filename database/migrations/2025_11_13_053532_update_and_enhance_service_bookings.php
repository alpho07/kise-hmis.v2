<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            //$table->foreignId('client_id')->after('visit_id')->constrained()->cascadeOnDelete();
            //$table->foreignId('department_id')->after('service_id')->constrained()->cascadeOnDelete();
            //$table->enum('booking_type', ['single', 'recurring'])->default('single')->after('department_id');
            //$table->integer('session_count')->default(1)->after('booking_type');
            //$table->integer('estimated_duration')->nullable()->after('session_count')->comment('Duration in minutes');
            //$table->integer('priority_level')->default(3)->after('estimated_duration')->comment('1=High, 3=Normal, 5=Low');
            //$table->enum('payment_status', ['pending', 'paid', 'cancelled'])->default('pending')->after('priority_level');
            //$table->enum('service_status', ['scheduled', 'in_progress', 'completed', 'no_show', 'cancelled'])->default('scheduled')->after('payment_status');
            //$table->foreignId('assigned_provider_id')->nullable()->after('service_status')->constrained('users')->nullOnDelete();
            
            //$table->index('client_id');
            //$table->index('department_id');
            //$table->index('booking_type');
            //$table->index('payment_status');
            //$table->index('service_status');
            //$table->index(['booking_date', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['assigned_provider_id']);
            
            $table->dropIndex(['client_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['booking_type']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['service_status']);
            $table->dropIndex(['booking_date', 'payment_status']);
            
            $table->dropColumn([
                'client_id',
                'department_id',
                'booking_type',
                'session_count',
                'estimated_duration',
                'priority_level',
                'payment_status',
                'service_status',
                'assigned_provider_id',
            ]);
        });
    }
};