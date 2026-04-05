<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Remove duplicate rows keeping the most recently updated one
        DB::statement("
            DELETE sip1 FROM service_insurance_prices sip1
            INNER JOIN service_insurance_prices sip2
            ON sip1.service_id = sip2.service_id
               AND sip1.insurance_provider_id = sip2.insurance_provider_id
               AND sip1.updated_at < sip2.updated_at
        ");

        Schema::table('service_insurance_prices', function (Blueprint $table) {
            $table->unique(['service_id', 'insurance_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_insurance_prices', function (Blueprint $table) {
            $table->dropUnique(['service_id', 'insurance_provider_id']);
        });
    }
};
