<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InternalReferral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'client_id',
        'from_department_id',
        'to_department_id',
        'referring_provider_id',
        'accepting_provider_id',
        'service_id',
        'priority',
        'status',
        'clinical_reason',
        'findings',
        'investigations_done',
        'recommendations',
        'acceptance_notes',
        'rejection_reason',
        'outcome',
        'service_request_id',
        'service_booking_id',
        'referred_at',
        'accepted_at',
        'rejected_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function referringProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_provider_id');
    }

    public function acceptingProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepting_provider_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    // Scopes
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'accepted', 'in_progress']);
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('to_department_id', $departmentId);
    }

    // Helper Methods
    
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'accepted', 'in_progress']);
    }

    public function accept($providerId, $notes = null): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepting_provider_id' => $providerId,
            'acceptance_notes' => $notes,
        ]);
    }

    public function reject($reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function complete($outcome): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'outcome' => $outcome,
        ]);
    }
}