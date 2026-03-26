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
        Schema::table('assessment_auto_referrals', function (Blueprint $table) {
            // Drop the FK constraint, make nullable, re-add nullable FK
            $table->dropForeign(['form_response_id']);
            $table->foreignId('form_response_id')
                ->nullable()
                ->change();
            $table->foreign('form_response_id')
                ->references('id')
                ->on('assessment_form_responses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_auto_referrals', function (Blueprint $table) {
            $table->dropForeign(['form_response_id']);
            $table->foreignId('form_response_id')
                ->nullable(false)
                ->change();
            $table->foreign('form_response_id')
                ->references('id')
                ->on('assessment_form_responses')
                ->cascadeOnDelete();
        });
    }
};
