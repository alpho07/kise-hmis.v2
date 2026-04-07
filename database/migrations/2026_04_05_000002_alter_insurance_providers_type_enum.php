<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL: widen ENUM and normalize values
        // SQLite (used in testing) stores ENUM as TEXT — no ALTER needed, skip gracefully
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government', 'government_scheme', 'ecitizen') NOT NULL DEFAULT 'private'");
        }

        // Normalize old values to new ones (works on both MySQL and SQLite)
        DB::update("UPDATE insurance_providers SET type = 'government_scheme' WHERE type IN ('public', 'government')");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('government_scheme', 'ecitizen', 'private') NOT NULL DEFAULT 'private'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government', 'government_scheme', 'ecitizen') NOT NULL DEFAULT 'private'");
        }
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'government_scheme'");
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'ecitizen'");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government') NOT NULL DEFAULT 'private'");
        }
    }
};
