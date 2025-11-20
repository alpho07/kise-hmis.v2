<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBranch
{
    /**
     * Boot the BelongsToBranch trait
     */
    protected static function bootBelongsToBranch(): void
    {
        // Automatically set branch_id when creating records
        static::creating(function (Model $model) {
            if (! $model->isDirty('branch_id') && auth()->check()) {
                // Get user's branch
                $userBranch = auth()->user()->branch_id;
                
                // Super admin can create for any branch if branch_id is set
                if (auth()->user()->hasRole('super_admin') && $model->branch_id) {
                    return;
                }
                
                // Otherwise, use user's branch
                if ($userBranch) {
                    $model->branch_id = $userBranch;
                }
            }
        });

        // Apply global scope for non-super-admin users
        static::addGlobalScope('branch', function (Builder $builder) {
            if (auth()->check() && !auth()->user()->hasRole('super_admin')) {
                $userBranch = auth()->user()->branch_id;
                if ($userBranch) {
                    $builder->where('branch_id', $userBranch);
                }
            }
        });
    }

    /**
     * Get the branch that owns the model
     */
    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    /**
     * Scope to filter by specific branch
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope to get all records across branches (super admin only)
     */
    public function scopeAllBranches(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}