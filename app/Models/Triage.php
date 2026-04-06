<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Triage extends Model
{
    use HasFactory, SoftDeletes, LogsActivity,BelongsToBranch;

    protected $table = 'triages';

    protected $fillable = [
        'visit_id',
        'client_id',
        'branch_id',
        'triage_number',
        
        // Vital Signs
        'temperature',
        'heart_rate',
        'respiratory_rate',
        'systolic_bp',
        'diastolic_bp',
        'oxygen_saturation',
        'weight',
        'height',
        'bmi',
        'pain_scale',
        'consciousness_level',
        
        // Risk Assessment
        'red_flags',
        'has_red_flags',
        'safeguarding_concerns',
        'has_safeguarding_concerns',
        'risk_level',
        'risk_flag',
        'risk_score',
        
        // Clearance & Decision
        'triage_status',
        'clearance_status',
        'next_step',
        
        // Notes
        'notes',
        'handover_summary',
        'pending_actions',
        
        // Crisis Protocol
        'crisis_protocol_activated',
        'crisis_activated_at',
        
        // Audit & Expiration
        'triaged_by',
        'cleared_by',
        'cleared_at',
        'is_expired',
        'expires_at',
    ];

    protected $casts = [
        // Vital Signs
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'oxygen_saturation' => 'integer',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'systolic_bp' => 'integer',
        'diastolic_bp' => 'integer',
        'pain_scale' => 'integer',
        
        // JSON fields
        'red_flags' => 'array',
        'safeguarding_concerns' => 'array',
        
        // Booleans
        'has_red_flags' => 'boolean',
        'has_safeguarding_concerns' => 'boolean',
        'crisis_protocol_activated' => 'boolean',
        'is_expired' => 'boolean',
        
        // Timestamps
        'cleared_at' => 'datetime',
        'crisis_activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate triage number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($triage) {
            if (!$triage->triage_number) {
                $triage->triage_number = 'TRI-' . date('Ymd') . '-' . str_pad(
                    Triage::whereDate('created_at', today())->count() + 1, 
                    4, 
                    '0', 
                    STR_PAD_LEFT
                );
            }
            
            // Auto-calculate risk score
            $triage->autoCalculateRiskScore();
            
            // Set expiration (7 days from now)
            if (!$triage->expires_at) {
                $triage->expires_at = now()->addDays(7);
            }
        });
    }

    /**
     * Relationships
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function redFlags(): HasMany
    {
        return $this->hasMany(TriageRedFlag::class);
    }

    /**
     * Check if cleared for service
     */
    public function isClearedForService(): bool
    {
        return $this->clearance_status === 'cleared_for_service' 
            && !$this->is_expired;
    }

    /**
     * Check if medical hold
     */
    public function hasMedicalHold(): bool
    {
        return $this->clearance_status === 'medical_hold';
    }

    /**
     * Check if crisis protocol
     */
    public function isCrisis(): bool
    {
        return $this->clearance_status === 'crisis_protocol' 
            || $this->crisis_protocol_activated;
    }

    /**
     * Get queue priority based on risk flag
     */
    public function getQueuePriority(): string
    {
        return match($this->risk_flag) {
            'crisis' => 'critical',
            'high' => 'urgent',
            'medium' => 'high',
            'low' => 'normal',
            default => 'normal'
        };
    }

    /**
     * Auto-calculate risk score based on vital signs and flags
     */
    public function autoCalculateRiskScore(): void
    {
        $score = 0;
        
        // Oxygen Saturation (Critical indicator)
        if ($this->oxygen_saturation < 90) {
            $score += 40;
        } elseif ($this->oxygen_saturation < 92) {
            $score += 30;
        } elseif ($this->oxygen_saturation < 95) {
            $score += 15;
        }
        
        // Temperature
        if ($this->temperature >= 39.5) {
            $score += 25;
        } elseif ($this->temperature >= 38.5) {
            $score += 20;
        } elseif ($this->temperature < 35.5) {
            $score += 20;
        }
        
        // Heart Rate
        if ($this->heart_rate > 130 || $this->heart_rate < 40) {
            $score += 20;
        } elseif ($this->heart_rate > 120 || $this->heart_rate < 50) {
            $score += 15;
        }
        
        // Respiratory Rate
        if ($this->respiratory_rate > 28 || $this->respiratory_rate < 10) {
            $score += 20;
        } elseif ($this->respiratory_rate > 24 || $this->respiratory_rate < 12) {
            $score += 15;
        }
        
        // Blood Pressure (systolic)
        if ($this->systolic_bp > 180 || $this->systolic_bp < 90) {
            $score += 15;
        }
        
        // Red Flags (Major indicator)
        if ($this->has_red_flags) {
            $score += 40;
        }
        
        // Safeguarding Concerns (Major indicator)
        if ($this->has_safeguarding_concerns) {
            $score += 35;
        }
        
        // Pain Scale
        if ($this->pain_scale >= 8) {
            $score += 15;
        } elseif ($this->pain_scale >= 6) {
            $score += 10;
        }
        
        // Consciousness Level
        if ($this->consciousness_level === 'unresponsive') {
            $score += 50;
        } elseif ($this->consciousness_level === 'confused') {
            $score += 20;
        } elseif ($this->consciousness_level === 'drowsy') {
            $score += 10;
        }
        
        $this->risk_score = min($score, 100); // Cap at 100
        
        // Auto-set risk flag based on score
        if ($this->risk_score >= 70) {
            $this->risk_flag = 'crisis';
            $this->clearance_status = 'crisis_protocol';
        } elseif ($this->risk_score >= 50) {
            $this->risk_flag = 'high';
            $this->clearance_status = 'medical_hold';
        } elseif ($this->risk_score >= 30) {
            $this->risk_flag = 'medium';
        } else {
            $this->risk_flag = 'low';
        }
    }

    /**
     * Activate crisis protocol
     */
    public function activateCrisisProtocol(): void
    {
        $this->update([
            'crisis_protocol_activated' => true,
            'crisis_activated_at' => now(),
            'clearance_status' => 'crisis_protocol',
            'risk_flag' => 'crisis',
        ]);
        
        // TODO: Trigger notifications to crisis team, safeguarding, security
        // This will be implemented in Phase 2
    }

    /**
     * Clear medical hold
     */
    public function clearMedicalHold(string $notes): void
    {
        $this->update([
            'clearance_status' => 'cleared_for_service',
            'cleared_by' => auth()->id(),
            'cleared_at' => now(),
            'notes' => $this->notes . "\n\nCleared: " . $notes,
        ]);
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['is_expired' => true]);
    }

    /**
     * Scope for cleared triages
     */
    public function scopeCleared($query)
    {
        return $query->where('clearance_status', 'cleared_for_service')
            ->where('is_expired', false);
    }

    /**
     * Scope for medical holds
     */
    public function scopeMedicalHolds($query)
    {
        return $query->where('clearance_status', 'medical_hold');
    }

    /**
     * Scope for crisis cases
     */
    public function scopeCrisis($query)
    {
        return $query->where('clearance_status', 'crisis_protocol')
            ->orWhere('crisis_protocol_activated', true);
    }

    /**
     * Scope for today's triages
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['clearance_status', 'risk_flag', 'crisis_protocol_activated'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}