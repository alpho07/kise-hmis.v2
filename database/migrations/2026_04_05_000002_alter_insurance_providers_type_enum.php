<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Widen ENUM to include all old and new values so the UPDATE can succeed
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government', 'government_scheme', 'ecitizen') NOT NULL DEFAULT 'private'");

        // Normalize old values to new ones
        DB::update("UPDATE insurance_providers SET type = 'government_scheme' WHERE type IN ('public', 'government')");

        // Tighten to final ENUM
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('government_scheme', 'ecitizen', 'private') NOT NULL DEFAULT 'private'");
    }

    public function down(): void
    {
        // Widen first so we can update safely
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government', 'government_scheme', 'ecitizen') NOT NULL DEFAULT 'private'");
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'government_scheme'");
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'ecitizen'");
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government') NOT NULL DEFAULT 'private'");
    }
};
