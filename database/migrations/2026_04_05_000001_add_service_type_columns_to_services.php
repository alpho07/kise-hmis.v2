<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->enum('service_type', ['assessment', 'therapy', 'assistive_technology', 'consultation'])
                  ->default('assessment')
                  ->after('category');
            $table->boolean('requires_sessions')->default(false)->after('service_type');
            $table->unsignedTinyInteger('default_session_count')->nullable()->after('requires_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'requires_sessions', 'default_session_count']);
        });
    }
};
