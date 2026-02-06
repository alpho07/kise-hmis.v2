<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Queue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'branch_id',
        'visit_id',
        'name',
        'date',
        'max_capacity',
        'current_number',
        'status',
        'queue_display_name',
    ];

    protected $casts = [
        'date' => 'date',
        'max_capacity' => 'integer',
        'current_number' => 'integer',
    ];

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the branch
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all entries in this queue
     */
    public function entries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * Get waiting entries
     */
    public function waitingEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class)->where('status', 'waiting');
    }

    /**
     * Get serving entries
     */
    public function servingEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class)->where('status', 'serving');
    }

    /**
     * Get completed entries
     */
    public function completedEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class)->where('status', 'completed');
    }

    /**
     * Get queue history
     */
    public function history(): HasMany
    {
        return $this->hasMany(QueueHistory::class);
    }

    /**
     * Get next queue number
     */
    public function getNextNumber(): int
    {
        $this->increment('current_number');
        return $this->current_number;
    }

    /**
     * Get waiting count
     */
    public function getWaitingCountAttribute(): int
    {
        return $this->waitingEntries()->count();
    }

    /**
     * Get average wait time
     */
    public function getAverageWaitTimeAttribute(): ?int
    {
        return $this->entries()
            ->whereNotNull('serving_started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, joined_at, serving_started_at)) as avg_wait')
            ->value('avg_wait');
    }

    /**
     * Scope for active queues
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for today's queues
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope by department
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}