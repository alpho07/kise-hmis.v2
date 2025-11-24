<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MedicalHold extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'visit_id',
        'client_id',
        'triage_id',
        'branch_id',
        'reason',
        'severity',
        'referred_to',
        'status',
        'cleared_by',
        'cleared_at',
        'clearance_notes',
        'created_by',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function triage(): BelongsTo
    {
        return $this->belongsTo(Triage::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Clear the medical hold
     */
    public function clear(string $notes): void
    {
        $this->update([
            'status' => 'cleared',
            'cleared_by' => auth()->id(),
            'cleared_at' => now(),
            'clearance_notes' => $notes,
        ]);

        // Update triage clearance status if exists
        if ($this->triage) {
            $this->triage->update([
                'clearance_status' => 'cleared_for_service',
                'cleared_by' => auth()->id(),
                'cleared_at' => now(),
            ]);
        }
    }

    /**
     * Escalate the medical hold
     */
    public function escalate(string $reason): void
    {
        $this->update([
            'status' => 'escalated',
            'clearance_notes' => 'Escalated: ' . $reason,
        ]);
    }

    /**
     * Check if hold is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope for active holds
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for cleared holds
     */
    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    /**
     * Scope for today's holds
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
            ->logOnly(['status', 'severity', 'cleared_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}