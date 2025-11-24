<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADD BRANCH AND STATUS TO USERS TABLE
     * Enhance users table for multi-tenancy and status tracking
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Branch Assignment (Multi-Tenancy)
            $table->foreignId('branch_id')->nullable()->after('id')
                ->constrained()->nullOnDelete()
                ->comment('User assigned branch for multi-tenancy');
            
            // User Status
            $table->boolean('is_active')->default(true)->after('password')
                ->comment('User account active status');
            
            // Contact Information
            $table->string('phone', 20)->nullable()->after('email')
                ->comment('User phone number');
            
            // Professional Information
            $table->string('employee_id', 50)->unique()->nullable()->after('phone')
                ->comment('Employee/Staff ID number');
            $table->string('designation', 100)->nullable()->after('employee_id')
                ->comment('Job title/designation');
            
            // Settings
            $table->json('preferences')->nullable()->after('remember_token')
                ->comment('User preferences (language, theme, etc.)');
            
            // Last Activity
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at')
                ->comment('Last successful login timestamp');
            
            // Soft Delete
            $table->softDeletes()->after('updated_at');
            
            // Indexes
            $table->index('branch_id', 'idx_users_branch');
            $table->index('is_active', 'idx_users_active');
            $table->index('employee_id', 'idx_users_employee');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex('idx_users_branch');
            $table->dropIndex('idx_users_active');
            $table->dropIndex('idx_users_employee');
            
            $table->dropColumn([
                'branch_id',
                'is_active',
                'phone',
                'employee_id',
                'designation',
                'preferences',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};