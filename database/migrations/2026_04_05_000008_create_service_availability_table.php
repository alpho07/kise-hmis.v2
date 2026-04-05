<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->enum('reason_code', ['staff_absent', 'equipment_unavailable', 'public_holiday', 'training', 'other'])->nullable();
            $table->string('comment', 500)->nullable();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability');
    }
};
