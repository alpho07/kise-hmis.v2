<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->nullOnDelete()->constrained();
            $table->foreignId('insurance_provider_id')->nullable()->after('branch_id')->nullOnDelete()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['insurance_provider_id']);
            $table->dropColumn(['branch_id', 'insurance_provider_id']);
        });
    }
};
