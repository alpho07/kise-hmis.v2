<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_sessions', function (Blueprint $table) {
            $table->enum('progress_status', ['improving', 'stable', 'regressing', 'completed'])->nullable()->after('attendance');
            $table->date('next_session_date')->nullable()->after('progress_status');
            $table->unsignedTinyInteger('session_sequence')->nullable()->after('next_session_date');
        });
    }

    public function down(): void
    {
        Schema::table('service_sessions', function (Blueprint $table) {
            $table->dropColumn(['progress_status', 'next_session_date', 'session_sequence']);
        });
    }
};
