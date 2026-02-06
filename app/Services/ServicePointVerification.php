<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * ServicePointVerification Model
 * 
 * Tracks customer care verification at service point entrance.
 * Integrates with existing Visit, Client, ServiceBooking models.
 */
class ServicePointVerification extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'visit_id',
        'client_id',
        'service_booking_id',
        'department_id',
        'verified_by',
        'verification_time',
        'verification_status',
        'payment_verified',
        'booking_verified',
        'routing_verified',
        'service_available',
        'provider_assigned_id',
        'room_assigned',
        'unavailability_reason',
        'rescheduled_to',
        'sensitization_notes',
        'signed_in_at',
        'notes',
    ];

    protected $casts = [
        'verification_time' => 'datetime',
        'rescheduled_to' => 'datetime',
        'signed_in_at' => 'datetime',
        'payment_verified' => 'boolean',
        'booking_verified' => 'boolean',
        'routing_verified' => 'boolean',
        'service_available' => 'boolean',
    ];

    /**
     * RELATIONSHIPS
     */
    
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function providerAssigned(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_assigned_id');
    }

    /**
     * SCOPES
     */
    
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('verification_time', today());
    }

    /**
     * METHODS
     */
    
    public function signIn(): void
    {
        $this->update([
            'signed_in_at' => now(),
            'verification_status' => 'verified',
        ]);
        
        // Create service session
        if ($this->serviceBooking) {
            ServiceSession::firstOrCreate([
                'service_booking_id' => $this->serviceBooking->id,
                'visit_id' => $this->visit_id,
                'client_id' => $this->client_id,
                'service_id' => $this->serviceBooking->service_id,
            ], [
                'provider_id' => $this->provider_assigned_id,
                'session_date' => today(),
                'signed_in_by' => $this->verified_by,
                'status' => 'scheduled',
                'attendance_status' => 'present',
            ]);

            $this->serviceBooking->update([
                'service_status' => 'in_progress',
                'assigned_provider_id' => $this->provider_assigned_id,
            ]);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['verification_status', 'signed_in_at'])
            ->logOnlyDirty();
    }
}