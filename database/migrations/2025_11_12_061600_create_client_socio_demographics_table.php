<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_socio_demographics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed', 'other'])->nullable();
            $table->enum('living_arrangement', ['with_family', 'institution', 'alone', 'other'])->nullable();
            $table->integer('household_size')->nullable();
            $table->json('source_of_support')->nullable();
            $table->string('other_support_source', 200)->nullable();
            $table->string('primary_language', 100)->nullable();
            $table->json('other_languages')->nullable();
            $table->text('socio_notes')->nullable();
            $table->timestamps();
            
            $table->index('client_id');
            $table->index('marital_status');
            $table->index('living_arrangement');
            $table->index('primary_language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_socio_demographics');
    }
};