<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Section C: persist the NCPWD registration radio + card verification status
        Schema::table('client_disabilities', function (Blueprint $table) {
            $table->string('ncpwd_registered', 20)->nullable()->after('disability_notes');          // yes / no / unknown
            $table->string('ncpwd_verification_status', 30)->nullable()->after('ncpwd_registered'); // seen / uploaded / verified
        });

        // Section D: free-text "other" overflows for ENUM parent columns, plus school-enrolled flag
        Schema::table('client_socio_demographics', function (Blueprint $table) {
            $table->string('marital_status_other', 100)->nullable()->after('marital_status');
            $table->string('living_arrangement_other', 100)->nullable()->after('living_arrangement');
            $table->string('school_enrolled', 10)->nullable()->after('other_support_source'); // yes / no
        });
    }

    public function down(): void
    {
        Schema::table('client_disabilities', function (Blueprint $table) {
            $table->dropColumn(['ncpwd_registered', 'ncpwd_verification_status']);
        });

        Schema::table('client_socio_demographics', function (Blueprint $table) {
            $table->dropColumn(['marital_status_other', 'living_arrangement_other', 'school_enrolled']);
        });
    }
};
