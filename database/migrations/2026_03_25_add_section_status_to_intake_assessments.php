<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->json('section_status')->nullable()->after('assessed_at');
            $table->boolean('is_finalized')->default(false)->after('section_status');
            $table->timestamp('finalized_at')->nullable()->after('is_finalized');
        });
    }

    public function down(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->dropColumn(['section_status', 'is_finalized', 'finalized_at']);
        });
    }
};
