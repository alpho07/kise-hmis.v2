<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QueueEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'queue_id',
        'visit_id',
        'client_id',
        'service_booking_id',
        'service_id',
        'department_id',
        'queue_number',
        'priority_level',
        'estimated_duration',
        'status',
        'joined_at',
        'called_at',
        'serving_started_at',
        'serving_completed_at',
        'notes',
        'no_show',
        'called_by',
    ];

    protected $casts = [
        'queue_number' => 'integer',
        'priority_level' => 'integer',
        'estimated_duration' => 'integer',
        'joined_at' => 'datetime',
        'called_at' => 'datetime',
        'serving_started_at' => 'datetime',
        'serving_completed_at' => 'datetime',
        'no_show' => 'boolean',
    ];

    public function calledBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'called_by');
}

    /**
     * Boot method to set queue number and joined time
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            if (!$entry->queue_number) {
                $entry->queue_number = $entry->queue->getNextNumber();
            }
            if (!$entry->joined_at) {
                $entry->joined_at = now();
            }
        });
    }

    /**
     * Get the queue
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * Get the visit
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the service booking (CRITICAL LINK)
     */
    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    /**
     * Get the service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Call this client from queue
     */
    public function call(): void
    {
        $this->update([
            'status' => 'called',
            'called_at' => now(),
        ]);
    }

    /**
     * Start serving this client
     */
    public function startServing(): void
    {
        $this->update([
            'status' => 'serving',
            'serving_started_at' => now(),
        ]);
    }

    /**
     * Complete service for this client
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'serving_completed_at' => now(),
        ]);

        // Update service booking status
        if ($this->serviceBooking) {
            $this->serviceBooking->update(['service_status' => 'completed']);
        }

        // Move to queue history
        QueueHistory::create([
            'queue_id' => $this->queue_id,
            'queue_entry_id' => $this->id,
            'client_id' => $this->client_id,
            'service_id' => $this->service_id,
            'queue_number' => $this->queue_number,
            'wait_time_minutes' => $this->getWaitTimeAttribute(),
            'service_time_minutes' => $this->getServiceTimeAttribute(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Get wait time in minutes
     */
    public function getWaitTimeAttribute(): ?int
    {
        if ($this->serving_started_at && $this->joined_at) {
            return $this->joined_at->diffInMinutes($this->serving_started_at);
        }
        return null;
    }

    /**
     * Get service time in minutes
     */
    public function getServiceTimeAttribute(): ?int
    {
        if ($this->serving_completed_at && $this->serving_started_at) {
            return $this->serving_started_at->diffInMinutes($this->serving_completed_at);
        }
        return null;
    }

    /**
     * Scope for waiting entries
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope for called entries
     */
    public function scopeCalled($query)
    {
        return $query->where('status', 'called');
    }

    /**
     * Scope for serving entries
     */
    public function scopeServing($query)
    {
        return $query->where('status', 'serving');
    }

    /**
     * Scope for completed entries
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for high priority
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority_level', 1);
    }

    /**
     * Scope ordered by priority then queue number
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority_level')->orderBy('queue_number');
    }
}