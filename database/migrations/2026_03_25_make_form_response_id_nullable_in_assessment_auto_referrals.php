<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assessment_auto_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('form_response_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_auto_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('form_response_id')->nullable(false)->change();
        });
    }
};
