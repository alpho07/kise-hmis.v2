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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id', 50)->unique()->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'designation')) {
                $table->string('designation', 100)->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('users', 'employee_id') ? 'employee_id' : null,
                Schema::hasColumn('users', 'designation') ? 'designation' : null,
                Schema::hasColumn('users', 'last_login_at') ? 'last_login_at' : null,
                Schema::hasColumn('users', 'preferences') ? 'preferences' : null,
                Schema::hasColumn('users', 'deleted_at') ? 'deleted_at' : null,
            ]));
        });
    }
};
