<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Traits\BelongsToBranch as TraitsBelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QueueEntry extends Model
{
    use HasFactory, SoftDeletes, TraitsBelongsToBranch;

    protected $fillable = [
        'branch_id',
        'visit_id',
        'client_id',
        'service_id',
        'department_id',
        'service_provider_id',
        'queue_number',
        'status',
        'priority_level',
        'checked_in_at',
        'called_at',
        'started_at',
        'completed_at',
        'estimated_wait_time',
        'actual_wait_time',
        'service_duration',
        'notes',
        'cancellation_reason',
        'transferred_from_department_id',
        'transferred_to_department_id',
        'transferred_at',
        'transferred_by',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'called_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'transferred_at' => 'datetime',
        'estimated_wait_time' => 'integer',
        'actual_wait_time' => 'integer',
        'service_duration' => 'integer',
    ];

    protected $attributes = [
        'status' => 'waiting',
        'priority' => 'normal',
    ];

    /**
     * Boot the model and set up observers
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate wait times and durations on update
        static::updating(function ($queueEntry) {
            if ($queueEntry->isDirty('started_at') && $queueEntry->started_at) {
                $queueEntry->actual_wait_time = $queueEntry->checked_in_at
                    ->diffInMinutes($queueEntry->started_at);
            }

            if ($queueEntry->isDirty('completed_at') && $queueEntry->completed_at) {
                $queueEntry->service_duration = $queueEntry->started_at
                    ->diffInMinutes($queueEntry->completed_at);
            }
        });
    }

    // ===================================================================
    // RELATIONSHIPS
    // ===================================================================

    /**
     * The branch this queue entry belongs to
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The visit this queue entry is associated with
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * The client in this queue entry
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The service being provided
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The department handling this queue entry
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The service provider assigned to this queue entry
     */
    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_provider_id');
    }

    /**
     * The user who verified this queue entry at service point
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function serviceBooking(): HasMany
    {
        return $this->hasMany(ServiceBooking::class, 'visit_id');
    }

    /**
     * The department this entry was transferred from
     */
    public function transferredFromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'transferred_from_department_id');
    }

    /**
     * The department this entry was transferred to
     */
    public function transferredToDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'transferred_to_department_id');
    }

    /**
     * The user who transferred this queue entry
     */
    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    // ===================================================================
    // SCOPES
    // ===================================================================

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get waiting entries
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope to get ready entries
     */
    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    /**
     * Scope to get in-service entries
     */
    public function scopeInService($query)
    {
        return $query->where('status', 'in_service');
    }

    /**
     * Scope to get completed entries
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get cancelled entries
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to filter by department
     */
    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by service provider
     */
    public function scopeForProvider($query, int $providerId)
    {
        return $query->where('service_provider_id', $providerId);
    }

    /**
     * Scope to filter by priority
     */
    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get high priority entries
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope to get urgent priority entries
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Scope to get entries verified at service point
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope to get unverified entries
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Scope to get today's queue entries
     */
    public function scopeToday($query)
    {
        return $query->whereDate('checked_in_at', today());
    }

    /**
     * Scope to order by queue position
     */
    public function scopeByQueueOrder($query)
    {
        return $query->orderByRaw("
            CASE priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'normal' THEN 3
                WHEN 'low' THEN 4
            END
        ")->orderBy('checked_in_at', 'asc');
    }

    // ===================================================================
    // ACCESSOR ATTRIBUTES
    // ===================================================================

    /**
     * Get the full queue number with department prefix
     */
    public function getFullQueueNumberAttribute(): string
    {
        $departmentCode = $this->department?->code ?? 'UNK';
        return "{$departmentCode}-{$this->queue_number}";
    }

    /**
     * Check if entry is waiting
     */
    public function getIsWaitingAttribute(): bool
    {
        return $this->status === 'waiting';
    }

    /**
     * Check if entry is ready
     */
    public function getIsReadyAttribute(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Check if entry is in service
     */
    public function getIsInServiceAttribute(): bool
    {
        return $this->status === 'in_service';
    }

    /**
     * Check if entry is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if entry is cancelled
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if entry is verified at service point
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Get current wait time in minutes
     */
    public function getCurrentWaitTimeAttribute(): ?int
    {
        if ($this->status === 'waiting' || $this->status === 'ready') {
            return $this->checked_in_at->diffInMinutes(now());
        }

        return $this->actual_wait_time;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'waiting' => 'warning',
            'ready' => 'info',
            'in_service' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'transferred' => 'secondary',
            default => 'gray',
        };
    }

    /**
     * Get priority badge color
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'normal' => 'info',
            'low' => 'gray',
            default => 'gray',
        };
    }

    // ===================================================================
    // HELPER METHODS
    // ===================================================================

    /**
     * Mark entry as ready (verified at service point)
     */
    public function markAsReady(?int $verifiedBy = null): self
    {
        $this->update([
            'status' => 'ready',
            'verified_at' => now(),
            'verified_by' => $verifiedBy ?? auth()->id(),
        ]);

        return $this;
    }

    /**
     * Call the next entry (assign to provider and start service)
     */
    public function callEntry(?int $providerId = null): self
    {
        $this->update([
            'status' => 'in_service',
            'service_provider_id' => $providerId ?? auth()->id(),
            'called_at' => now(),
            'started_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get the service request that created this queue entry (if any)
     */
    public function serviceRequest(): HasOne
    {
        return $this->hasOne(ServiceRequest::class);
    }

    /**
     * Check if queue entry was created from service request
     */
    public function isFromServiceRequest(): bool
    {
        return $this->serviceRequest()->exists();
    }

    /**
     * Complete the service
     */
    public function completeService(?string $notes = null): self
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        return $this;
    }

    /**
     * Cancel the queue entry
     */
    public function cancelEntry(string $reason): self
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Transfer to another department
     */
    public function transferToDepartment(int $toDepartmentId, ?int $transferredBy = null): self
    {
        $this->update([
            'status' => 'transferred',
            'transferred_from_department_id' => $this->department_id,
            'transferred_to_department_id' => $toDepartmentId,
            'department_id' => $toDepartmentId,
            'transferred_at' => now(),
            'transferred_by' => $transferredBy ?? auth()->id(),
            // Reset verification status for new department
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return $this;
    }

    /**
     * Get position in queue
     */
    public function getQueuePosition(): int
    {
        return static::query()
            ->where('department_id', $this->department_id)
            ->where('status', 'waiting')
            ->where(function ($query) {
                $query->where('priority', '>', $this->priority)
                    ->orWhere(function ($q) {
                        $q->where('priority', $this->priority)
                            ->where('checked_in_at', '<', $this->checked_in_at);
                    });
            })
            ->count() + 1;
    }

    /**
     * Calculate estimated wait time based on current queue
     */
    public function calculateEstimatedWaitTime(): int
    {
        $position = $this->getQueuePosition();
        $averageServiceTime = $this->department->average_service_time ?? 15; // default 15 minutes

        return $position * $averageServiceTime;
    }

    /**
     * Generate next queue number for department
     */
    public static function generateQueueNumber(int $departmentId, int $branchId): int
    {
        $lastEntry = static::query()
            ->where('department_id', $departmentId)
            ->where('branch_id', $branchId)
            ->whereDate('checked_in_at', today())
            ->orderBy('queue_number', 'desc')
            ->first();

        return $lastEntry ? $lastEntry->queue_number + 1 : 1;
    }

    /**
     * Check if entry can be called
     */
    public function canBeCalled(): bool
    {
        return in_array($this->status, ['waiting', 'ready']) && $this->is_verified;
    }

    /**
     * Check if entry can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_service';
    }

    /**
     * Check if entry can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Get the next entry in queue for a department
     */
    public static function getNextInQueue(int $departmentId): ?self
    {
        return static::query()
            ->forDepartment($departmentId)
            ->ready()
            ->verified()
            ->byQueueOrder()
            ->first();
    }

    /**
     * Get queue statistics for a department
     */
    public static function getQueueStats(int $departmentId): array
    {
        $today = today();

        return [
            'waiting' => static::forDepartment($departmentId)->waiting()->today()->count(),
            'ready' => static::forDepartment($departmentId)->ready()->today()->count(),
            'in_service' => static::forDepartment($departmentId)->inService()->today()->count(),
            'completed' => static::forDepartment($departmentId)->completed()->today()->count(),
            'cancelled' => static::forDepartment($departmentId)->cancelled()->today()->count(),
            'average_wait_time' => static::forDepartment($departmentId)
                ->completed()
                ->today()
                ->avg('actual_wait_time'),
            'average_service_time' => static::forDepartment($departmentId)
                ->completed()
                ->today()
                ->avg('service_duration'),
        ];
    }
}
