<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (testing) — initial migrations already updated; no action needed here.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE visits
                MODIFY COLUMN current_stage
                ENUM('reception','triage','intake','billing','queue','cashier','service','completed','deferred')
                NOT NULL DEFAULT 'reception'
            ");
            DB::statement("
                ALTER TABLE visit_stages
                MODIFY COLUMN stage
                ENUM('reception','triage','intake','billing','queue','cashier','service','completed','deferred')
                NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE visits
                MODIFY COLUMN current_stage
                ENUM('reception','triage','intake','billing','queue','service','completed','deferred')
                NOT NULL DEFAULT 'reception'
            ");
            DB::statement("
                ALTER TABLE visit_stages
                MODIFY COLUMN stage
                ENUM('reception','triage','intake','billing','queue','service','completed','deferred')
                NOT NULL
            ");
        }
    }
};
