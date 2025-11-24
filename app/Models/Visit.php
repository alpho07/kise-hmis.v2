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
        'visit_type',
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
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'is_appointment' => 'boolean',
        'is_emergency' => 'boolean',
        'requires_followup' => 'boolean',
        'service_available' => 'boolean',
    ];

    /**
     * Boot method to generate visit number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($visit) {
            if (!$visit->visit_number) {
                $visit->visit_number = 'VST-' . date('Ymd') . '-' . str_pad(Visit::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
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
        });
    }

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the branch
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who checked in
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
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
        return $this->hasOne(Triage::class);
    }

    /**
     * Get intake assessment
     */
    public function intakeAssessment(): HasOne
    {
        return $this->hasOne(IntakeAssessment::class);
    }

    /**
     * Get all service bookings
     */
    public function serviceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    /**
     * Get all invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all queue entries
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * Get all service sessions
     */
    public function serviceSessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    /**
     * Get medical holds
     */
    public function medicalHolds(): HasMany
    {
        return $this->hasMany(MedicalHold::class);
    }

    /**
     * Get crisis incidents
     */
    public function crisisIncidents(): HasMany
    {
        return $this->hasMany(CrisisIncident::class);
    }

    /**
     * ================================================================
     * NEW TRIAGE VERIFICATION METHODS (Phase 1)
     * ================================================================
     */

    /**
     * Check if visit has valid triage clearance
     */
    public function hasValidTriage(): bool
    {
        $triage = $this->currentTriage();
        return $triage && $triage->isClearedForService();
    }

    /**
     * Get current triage (non-expired)
     */
    public function currentTriage(): ?Triage
    {
        return $this->triage()
            ->where('is_expired', false)
            ->latest()
            ->first();
    }

    /**
     * Check if visit has medical hold
     */
    public function hasMedicalHold(): bool
    {
        $triage = $this->currentTriage();
        return $triage && $triage->hasMedicalHold();
    }

    /**
     * Check if crisis protocol is active
     */
    public function hasCrisisProtocol(): bool
    {
        $triage = $this->currentTriage();
        return $triage && $triage->isCrisis();
    }

    /**
     * Check if active medical hold exists
     */
    public function hasActiveMedicalHold(): bool
    {
        return $this->medicalHolds()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Block service access if not triaged or has holds
     * 
     * This is the CRITICAL safety check
     */
    public function canProceedToService(): bool
    {
        // Must have valid triage
        if (!$this->hasValidTriage()) {
            return false;
        }
        
        // Cannot have medical hold
        if ($this->hasMedicalHold() || $this->hasActiveMedicalHold()) {
            return false;
        }
        
        // Cannot have crisis protocol
        if ($this->hasCrisisProtocol()) {
            return false;
        }
        
        return true;
    }

    /**
     * Get blocking reason (for user feedback)
     */
    public function getServiceBlockReason(): ?string
    {
        if (!$this->triage()->exists()) {
            return 'No triage assessment completed';
        }

        $triage = $this->currentTriage();
        
        if (!$triage) {
            return 'Triage has expired - reassessment required';
        }

        if ($triage->hasMedicalHold()) {
            return 'Medical hold active - clearance required';
        }

        if ($triage->isCrisis()) {
            return 'Crisis protocol active - immediate intervention required';
        }

        if ($this->hasActiveMedicalHold()) {
            return 'Active medical hold pending clearance';
        }

        return null;
    }

    /**
     * Get triage priority for queue ordering
     */
    public function getTriagePriority(): string
    {
        $triage = $this->currentTriage();
        return $triage ? $triage->getQueuePriority() : 'normal';
    }

    /**
     * Check if triage is required for this visit type
     */
    public function requiresTriage(): bool
    {
        // All visits require triage except follow-up appointments for stable clients
        // This logic can be customized based on business rules
        return true;
    }

    /**
     * ================================================================
     * EXISTING METHODS (Keep as-is)
     * ================================================================
     */

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
        $currentStage = $this->stages()->where('stage', $this->current_stage)->latest()->first();
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
        ]);
    }

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
     * Scope for visits pending triage
     */
    public function scopePendingTriage($query)
    {
        return $query->whereDoesntHave('triage')
            ->orWhereHas('triage', function ($q) {
                $q->where('is_expired', true);
            });
    }

    /**
     * Scope for visits with medical holds
     */
    public function scopeWithMedicalHold($query)
    {
        return $query->whereHas('triage', function ($q) {
            $q->where('clearance_status', 'medical_hold');
        })->orWhereHas('medicalHolds', function ($q) {
            $q->where('status', 'active');
        });
    }

    /**
     * Scope for visits with crisis protocol
     */
    public function scopeWithCrisisProtocol($query)
    {
        return $query->whereHas('triage', function ($q) {
            $q->where('clearance_status', 'crisis_protocol')
                ->orWhere('crisis_protocol_activated', true);
        });
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['current_stage', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}