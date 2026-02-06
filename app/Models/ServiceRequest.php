<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * SERVICE REQUEST MODEL
 * 
 * Purpose: Handle mid-journey service additions
 * Integration: Works with existing ServiceBooking and QueueEntry
 * 
 * Key Methods:
 * - markPaid(): Update status after payment
 * - createService(): Auto-create ServiceBooking + QueueEntry
 * - complete(): Mark service as delivered
 */
class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'visit_id',
        'client_id',
        'requested_by',
        'requesting_department_id',
        'service_id',
        'service_department_id',
        'request_type',
        'priority',
        'status',
        'clinical_notes',
        'clinical_findings',
        'recommendations',
        'cost',
        'payment_method',
        'client_amount',
        'sponsor_amount',
        'service_booking_id',
        'queue_entry_id',
        'invoice_id',
        'requested_at',
        'paid_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'client_amount' => 'decimal:2',
        'sponsor_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====
    
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_department_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'service_department_id');
    }

    /**
     * Link to existing ServiceBooking (created after payment)
     */
    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    /**
     * Link to existing QueueEntry (created after payment)
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ===== KEY METHODS =====
    
    /**
     * Mark request as paid
     * Called from CashierQueueResource after payment
     */
    public function markPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Create service booking and queue entry
     * AUTO-INTEGRATES with existing ServiceBooking and QueueEntry tables
     * 
     * This is called after payment is complete
     */
    public function createService(): array
    {
        // Create ServiceBooking (links to existing table)
        $booking = ServiceBooking::create([
            'visit_id' => $this->visit_id,
            'client_id' => $this->client_id,
            'service_id' => $this->service_id,
            'department_id' => $this->service_department_id,
            'booking_date' => today(),
            'payment_status' => 'paid',
            'payment_method' => $this->payment_method,
            'service_status' => 'scheduled',
            'estimated_duration' => $this->service->duration_minutes ?? 30,
            'notes' => "Service requested by {$this->requestedBy->name} from {$this->requestingDepartment->name}",
        ]);

        // Create QueueEntry (links to existing table)
        // Uses existing QueueEntry model and createQueueEntry() method
        $queueEntry = $booking->createQueueEntry();

        // Update this request with links
        $this->update([
            'service_booking_id' => $booking->id,
            'queue_entry_id' => $queueEntry->id,
            'status' => 'service_created',
        ]);

        return [
            'booking' => $booking,
            'queue_entry' => $queueEntry,
        ];
    }

    /**
     * Complete the service request
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the request
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'recommendations' => $reason,
        ]);
    }

    // ===== SCOPES =====
    
    public function scopePendingPayment($query)
    {
        return $query->where('status', 'pending_payment');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeServiceCreated($query)
    {
        return $query->where('status', 'service_created');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('service_department_id', $departmentId);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', ['urgent', 'stat']);
    }

    // ===== COMPUTED ATTRIBUTES =====
    
    public function getIsPaidAttribute(): bool
    {
        return in_array($this->status, ['paid', 'service_created', 'in_progress', 'completed']);
    }

    public function getIsActiveAttribute(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending_payment' => 'warning',
            'paid' => 'info',
            'service_created' => 'primary',
            'in_progress' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'routine' => 'gray',
            'urgent' => 'warning',
            'stat' => 'danger',
            default => 'gray',
        };
    }

    // ===== ACTIVITY LOG =====
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'service_id', 'paid_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}