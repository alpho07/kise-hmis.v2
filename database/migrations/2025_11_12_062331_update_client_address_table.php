<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropColumn('county');
            
            $table->foreignId('county_id')->nullable()->after('city')->constrained()->nullOnDelete();
            $table->foreignId('sub_county_id')->nullable()->after('county_id')->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->after('sub_county_id')->constrained()->nullOnDelete();
            
            $table->index('county_id');
            $table->index('sub_county_id');
            $table->index('ward_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropForeign(['county_id']);
            $table->dropForeign(['sub_county_id']);
            $table->dropForeign(['ward_id']);
            
            $table->dropIndex(['county_id']);
            $table->dropIndex(['sub_county_id']);
            $table->dropIndex(['ward_id']);
            
            $table->dropColumn(['county_id', 'sub_county_id', 'ward_id']);
            
            $table->string('county', 50)->nullable();
        });
    }
};