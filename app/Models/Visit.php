<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Visit extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'client_id',
        'branch_id',
        'visit_number',
        'visit_type', // 'walk_in', 'appointment', 'follow_up'
        'visit_date',
        'visit_purpose',
        'referral_source',
        'is_appointment',
        'triage_path',
        'check_in_time',
        'check_out_time',
        'current_stage',
        'status',
        'checked_in_by',
        // Service Availability
        'service_available',
        'unavailability_reason',
        'unavailability_notes',
        // Flags
        'is_emergency',
        'requires_followup',
        // Notes
        'purpose_notes',
        // Queue & Payment
        'queue_number',
        'payment_verified_at',
        'payment_status', // 'pending', 'partial', 'paid'
        // Deferral
        'deferral_reason',
        'deferral_notes',
        'next_appointment_date',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'is_appointment' => 'boolean',
        'service_available' => 'boolean',
        'is_emergency' => 'boolean',
        'requires_followup' => 'boolean',
        'payment_verified_at' => 'datetime',
    ];

    /**
     * Boot method to generate visit number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($visit) {
            if (!$visit->visit_number) {
                $visit->visit_number = 'VST-' . date('Ymd') . '-' . str_pad(
                    Visit::whereDate('created_at', today())->count() + 1, 
                    4, 
                    '0', 
                    STR_PAD_LEFT
                );
            }
            if (!$visit->check_in_time) {
                $visit->check_in_time = now();
            }
            if (!$visit->current_stage) {
                $visit->current_stage = 'reception';
            }
            if (!$visit->status) {
                $visit->status = 'in_progress';
            }
            if (!$visit->payment_status) {
                $visit->payment_status = 'pending';
            }
        });
    }

    // ==========================================
    // CORE RELATIONSHIPS
    // ==========================================

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the branch
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the user who checked in
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }


/**
 * Get active service requests
 */
public function activeServiceRequests()
{
    return $this->serviceRequests()
        ->whereNotIn('status', ['completed', 'cancelled']);
}

/**
 * Check if visit has pending service requests
 */
public function hasPendingServiceRequests(): bool
{
    return $this->serviceRequests()
        ->where('status', 'pending_payment')
        ->exists();
}

    /**
     * Get all visit stages
     */
    public function stages(): HasMany
    {
        return $this->hasMany(VisitStage::class);
    }

    /**
     * Get triage record
     */
    public function triage(): HasOne
    {
        return $this->hasOne(Triage::class, 'visit_id');
    }

    /**
     * Get intake assessment
     */
    public function intakeAssessment(): HasOne
    {
        return $this->hasOne(IntakeAssessment::class, 'visit_id');
    }

    /**
     * Get functional screening
     */
    public function functionalScreening(): HasOne
    {
        return $this->hasOne(FunctionalScreening::class, 'visit_id');
    }

    // ==========================================
    // SERVICE & APPOINTMENT RELATIONSHIPS
    // ==========================================

    /**
     * Get all service bookings for this visit
     */
    public function serviceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class, 'visit_id');
    }

    /**
     * ✅ NEW: Get all service requests (mid-journey additions)
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'visit_id');
    }

    /**
     * ✅ NEW: Get appointment if this visit is from an appointment
     */
    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class, 'visit_id');
    }

    /**
     * Get all service sessions
     */
    public function serviceSessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class, 'visit_id');
    }

    /**
     * Get all queue entries for this visit
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class, 'visit_id');
    }

    // ==========================================
    // REFERRAL RELATIONSHIPS
    // ==========================================

    /**
     * ✅ NEW: Get internal referrals created during this visit
     */
    public function internalReferrals(): HasMany
    {
        return $this->hasMany(InternalReferral::class, 'visit_id');
    }

    /**
     * ✅ NEW: Get external referrals created during this visit
     */
    public function externalReferrals(): HasMany
    {
        return $this->hasMany(ExternalReferral::class, 'visit_id');
    }

    // ==========================================
    // PAYMENT & BILLING RELATIONSHIPS
    // ==========================================

    /**
     * Get all invoices (plural)
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'visit_id');
    }

    /**
     * Get the primary/current invoice (singular)
     * For cashier queue - gets the most recent unpaid invoice
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'visit_id')->latestOfMany();
    }

    /**
     * Get all payments for this visit
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'visit_id');
    }

    /**
     * Get insurance claims through invoices
     */
    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'visit_id');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Move to next stage
     */
    public function moveToStage(string $stage): void
    {
        $this->update(['current_stage' => $stage]);

        VisitStage::create([
            'visit_id' => $this->id,
            'stage' => $stage,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
    }

    /**
     * Complete current stage
     */
    public function completeStage(): void
    {
        $currentStage = $this->stages()
            ->where('stage', $this->current_stage)
            ->latest()
            ->first();
            
        if ($currentStage) {
            $currentStage->update([
                'completed_at' => now(),
                'status' => 'completed',
            ]);
        }
    }

    /**
     * Check out
     */
    public function checkOut(): void
    {
        $this->update([
            'check_out_time' => now(),
            'status' => 'completed',
            'current_stage' => 'completed',
        ]);
    }

    /**
     * ✅ NEW: Calculate total amount for this visit
     * Includes both initial services and mid-journey requests
     */
    public function calculateTotalAmount(): float
    {
        // Initial service bookings
        $servicesTotal = $this->serviceBookings()
            ->where('payment_status', '!=', 'paid')
            ->sum('total_cost');
        
        // Mid-journey service requests (pending payment)
        $requestsTotal = $this->serviceRequests()
            ->where('status', 'pending_payment')
            ->sum('cost');
        
        return $servicesTotal + $requestsTotal;
    }

    /**
     * ✅ NEW: Get all unpaid services (initial + mid-journey)
     */
    public function getUnpaidServicesAttribute()
    {
        $services = collect();
        
        // Add unpaid service bookings
        $services = $services->merge(
            $this->serviceBookings()
                ->where('payment_status', '!=', 'paid')
                ->with('service')
                ->get()
        );
        
        // Add pending service requests
        $pendingRequests = $this->serviceRequests()
            ->where('status', 'pending_payment')
            ->with('service')
            ->get();
        
        return [
            'bookings' => $this->serviceBookings()->where('payment_status', '!=', 'paid')->get(),
            'requests' => $pendingRequests,
            'total' => $this->calculateTotalAmount(),
        ];
    }

    /**
     * ✅ NEW: Check if all services are completed
     */
    public function allServicesCompleted(): bool
    {
        // Check service bookings
        $incompleteBookings = $this->serviceBookings()
            ->whereNotIn('service_status', ['completed', 'no_show'])
            ->count();
        
        // Check queue entries
        $incompleteQueue = $this->queueEntries()
            ->whereNotIn('status', ['completed', 'no_show'])
            ->count();
        
        return $incompleteBookings === 0 && $incompleteQueue === 0;
    }

    /**
     * ✅ NEW: Get service completion percentage
     */
    public function getServiceCompletionPercentage(): float
    {
        $totalServices = $this->serviceBookings()->count();
        
        if ($totalServices === 0) {
            return 0;
        }
        
        $completedServices = $this->serviceBookings()
            ->where('service_status', 'completed')
            ->count();
        
        return round(($completedServices / $totalServices) * 100, 2);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope for in progress visits
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for completed visits
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for today's visits
     */
    public function scopeToday($query)
    {
        return $query->whereDate('check_in_time', today());
    }

    /**
     * Scope for visits at a specific stage
     */
    public function scopeAtStage($query, string $stage)
    {
        return $query->where('current_stage', $stage);
    }

    /**
     * Scope for visits with unpaid invoices
     */
    public function scopeWithUnpaidInvoice($query)
    {
        return $query->whereHas('invoice', function ($q) {
            $q->where('payment_status', '!=', 'paid');
        });
    }

    /**
     * ✅ NEW: Scope for visits with pending service requests
     */
    public function scopeWithPendingRequests($query)
    {
        return $query->whereHas('serviceRequests', function ($q) {
            $q->where('status', 'pending_payment');
        });
    }

    /**
     * ✅ NEW: Scope for appointment-based visits
     */
    public function scopeFromAppointment($query)
    {
        return $query->where('visit_type', 'appointment')
            ->orWhereNotNull('is_appointment');
    }

    // ==========================================
    // ATTRIBUTES & ACCESSORS
    // ==========================================

    /**
     * Get total amount due for this visit
     */
    public function getTotalAmountDueAttribute(): float
    {
        return $this->invoice?->total_client_amount ?? 0;
    }

    /**
     * Get total amount paid for this visit
     */
    public function getTotalAmountPaidAttribute(): float
    {
        return $this->invoice?->amount_paid ?? 0;
    }

    /**
     * Get balance remaining
     */
    public function getBalanceAttribute(): float
    {
        return max(0, $this->total_amount_due - $this->total_amount_paid);
    }

    /**
     * Check if payment is complete
     */
    public function isPaymentComplete(): bool
    {
        return $this->invoice && $this->invoice->payment_status === 'paid';
    }

    /**
     * ✅ NEW: Check if visit has pending service requests
     */
    public function hasPendingRequests(): bool
    {
        return $this->serviceRequests()
            ->where('status', 'pending_payment')
            ->exists();
    }

    /**
     * ✅ NEW: Get visit duration in minutes
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->check_out_time) {
            return $this->check_in_time->diffInMinutes(now());
        }
        
        return $this->check_in_time->diffInMinutes($this->check_out_time);
    }

    /**
     * ✅ NEW: Get human-readable visit type
     */
    public function getVisitTypeDisplayAttribute(): string
    {
        return match($this->visit_type) {
            'walk_in' => 'Walk-in',
            'appointment' => 'Scheduled Appointment',
            'follow_up' => 'Follow-up Visit',
            'emergency' => 'Emergency',
            default => ucfirst($this->visit_type ?? 'Unknown'),
        };
    }

    // ==========================================
    // ACTIVITY LOG
    // ==========================================

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'current_stage', 
                'status', 
                'payment_status',
                'check_out_time',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}