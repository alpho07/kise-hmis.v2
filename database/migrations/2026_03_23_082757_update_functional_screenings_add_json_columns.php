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
        Schema::table('functional_screenings', function (Blueprint $table) {
            $table->string('age_band', 30)->nullable()->after('client_id');
            $table->json('screening_answers')->nullable()->after('age_band');
            if (!Schema::hasColumn('functional_screenings', 'overall_summary')) {
                $table->text('overall_summary')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('functional_screenings', function (Blueprint $table) {
            $table->dropColumn(['age_band', 'screening_answers']);
        });
    }
};
