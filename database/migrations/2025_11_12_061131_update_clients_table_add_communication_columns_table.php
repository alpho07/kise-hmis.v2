<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('primary_address')->nullable()->after('village');
            $table->string('landmark', 200)->nullable()->after('primary_address');
            $table->enum('preferred_communication', ['sms', 'phone', 'email'])->default('sms')->after('email');
            $table->boolean('consent_to_sms')->default(false)->after('preferred_communication');
            
            $table->index('preferred_communication');
            $table->index('consent_to_sms');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['preferred_communication']);
            $table->dropIndex(['consent_to_sms']);
            
            $table->dropColumn([
                'primary_address',
                'landmark',
                'preferred_communication',
                'consent_to_sms',
            ]);
        });
    }
};