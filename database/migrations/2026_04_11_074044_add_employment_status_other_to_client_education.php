<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the employment_status ENUM to include 'other' (MySQL/MariaDB only — SQLite stores as string natively)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE client_education MODIFY COLUMN employment_status ENUM('unemployed','employed','self_employed','student','retired','other') NULL");
        }

        if (!Schema::hasColumn('client_education', 'employment_status_other')) {
            Schema::table('client_education', function (Blueprint $table) {
                $table->string('employment_status_other', 200)->nullable()->after('employment_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_education', 'employment_status_other')) {
            Schema::table('client_education', function (Blueprint $table) {
                $table->dropColumn('employment_status_other');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE client_education MODIFY COLUMN employment_status ENUM('unemployed','employed','self_employed','student','retired') NULL");
        }
    }
};
