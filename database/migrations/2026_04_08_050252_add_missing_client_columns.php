<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds client columns that exist in the model $fillable / $casts
     * but were never included in any prior migration.
     *
     * All additions are guarded with hasColumn() so this is idempotent.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('uci');
            }
            if (!Schema::hasColumn('clients', 'registration_source')) {
                $table->string('registration_source', 100)->nullable()->after('registration_date');
            }
            if (!Schema::hasColumn('clients', 'photo')) {
                $table->string('photo', 255)->nullable()->comment('Profile photo path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['registration_date', 'registration_source', 'photo'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
