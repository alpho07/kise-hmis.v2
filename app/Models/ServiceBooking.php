<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ServiceBooking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'visit_id',
        'client_id',
        'service_id',
        'department_id',
        'booking_type',
        'session_count',
        'booked_by',
        'booking_date',
        'estimated_duration',
        'priority_level',
        'payment_status',
        'service_status',
        'assigned_provider_id',
        'notes',
        'status',
        'insurance_provider_id',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'session_count' => 'integer',
        'estimated_duration' => 'integer',
        'priority_level' => 'integer',
    ];

    public function insuranceProvider(): BelongsTo
{
    return $this->belongsTo(InsuranceProvider::class);
}

    /**
     * Boot method to auto-fill department_id from service
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (!$booking->department_id && $booking->service_id) {
                $service = Service::find($booking->service_id);
                if ($service) {
                    $booking->department_id = $service->department_id;
                    $booking->estimated_duration = $service->duration_minutes;
                }
            }
        });
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
     * Get the service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the department (for routing)
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who booked
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * Get the assigned provider
     */
    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_id');
    }

    /**
     * Get the invoice item for this booking
     */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    /**
     * Get the queue entry for this booking
     */
    public function queueEntry(): HasOne
    {
        return $this->hasOne(QueueEntry::class);
    }

    /**
     * Get all service sessions for this booking
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    /**
     * Check if booking is paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if booking is for today
     */
    public function isToday(): bool
    {
        return $this->booking_date->isToday();
    }

    /**
     * Check if booking is ready for queue
     */
    public function isReadyForQueue(): bool
    {
        return $this->isPaid() && $this->isToday() && $this->service_status === 'scheduled';
    }

    /**
     * Create queue entry for this booking
     */
    public function createQueueEntry(): ?QueueEntry
    {
        if (!$this->isReadyForQueue()) {
            return null;
        }

        // Find or create today's queue for the department
        $queue = Queue::firstOrCreate([
            'department_id' => $this->department_id,
            'branch_id' => $this->visit->branch_id,
            'date' => today(),
            'status' => 'active',
        ], [
            'name' => $this->department->name . ' - ' . today()->format('d/m/Y'),
        ]);

        // Create queue entry
        return QueueEntry::create([
            'queue_id' => $queue->id,
            'visit_id' => $this->visit_id,
            'client_id' => $this->client_id,
            'service_booking_id' => $this->id,
            'service_id' => $this->service_id,
            'department_id' => $this->department_id,
            'priority_level' => $this->priority_level,
            'estimated_duration' => $this->estimated_duration,
            'status' => 'waiting',
        ]);
    }

    /**
     * Scope for paid bookings
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for pending payment
     */
    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope for today's bookings
     */
    public function scopeToday($query)
    {
        return $query->whereDate('booking_date', today());
    }

    /**
     * Scope for scheduled bookings
     */
    public function scopeScheduled($query)
    {
        return $query->where('service_status', 'scheduled');
    }

    /**
     * Scope for completed bookings
     */
    public function scopeCompleted($query)
    {
        return $query->where('service_status', 'completed');
    }

    /**
     * Scope by department
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_status', 'service_status', 'booking_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}