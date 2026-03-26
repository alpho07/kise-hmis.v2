<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('intake_assessments', 'service_recommendations')) {
                $table->json('service_recommendations')->nullable()->after('recommendations');
            }
            if (! Schema::hasColumn('intake_assessments', 'referral_categories')) {
                $table->json('referral_categories')->nullable()->after('service_recommendations');
            }
            if (! Schema::hasColumn('intake_assessments', 'priority_level')) {
                $table->integer('priority_level')->default(3)->after('referral_categories')->comment('1=high, 5=low');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            $cols = ['service_recommendations', 'referral_categories', 'priority_level'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('intake_assessments', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
