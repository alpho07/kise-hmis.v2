<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Normalize any non-standard values before ALTER
        DB::update("
            UPDATE visits
            SET triage_path = 'standard'
            WHERE triage_path NOT IN ('standard', 'returning', 'medical_veto', 'crisis')
               OR triage_path IS NULL
        ");

        // SQLite (testing) stores ENUM as TEXT — MODIFY COLUMN not supported, skip
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE visits
                MODIFY COLUMN triage_path
                ENUM('standard', 'returning', 'medical_veto', 'crisis') NULL
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE visits MODIFY COLUMN triage_path VARCHAR(255) NULL");
        }
    }
};
