<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Remove duplicate rows keeping the most recently updated one
        // MySQL uses multi-table DELETE; SQLite (testing) uses a subquery approach
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                DELETE sip1 FROM service_insurance_prices sip1
                INNER JOIN service_insurance_prices sip2
                ON sip1.service_id = sip2.service_id
                   AND sip1.insurance_provider_id = sip2.insurance_provider_id
                   AND (
                       sip1.updated_at < sip2.updated_at
                       OR (sip1.updated_at = sip2.updated_at AND sip1.id < sip2.id)
                   )
            ");
        } else {
            // SQLite-compatible: delete non-max IDs per (service_id, insurance_provider_id) group
            DB::statement("
                DELETE FROM service_insurance_prices
                WHERE id NOT IN (
                    SELECT MAX(id)
                    FROM service_insurance_prices
                    GROUP BY service_id, insurance_provider_id
                )
            ");
        }

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
