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
        Schema::table('client_education', function (Blueprint $table) {
            $table->boolean('attendance_challenges')->nullable()->after('grade_level');
            $table->text('attendance_notes')->nullable()->after('attendance_challenges');
            $table->boolean('performance_concern')->nullable()->after('attendance_notes');
            $table->text('performance_notes')->nullable()->after('performance_concern');
        });
    }

    public function down(): void
    {
        Schema::table('client_education', function (Blueprint $table) {
            $table->dropColumn(['attendance_challenges', 'attendance_notes', 'performance_concern', 'performance_notes']);
        });
    }
};
