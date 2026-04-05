<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\InsuranceProvider;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'visit_id',
        'department_id',
        'service_id',
        'provider_id',
        'appointment_date',
        'appointment_time',
        'duration',
        'room_assigned',
        'appointment_type',
        'status',
        'payment_status',
        'payment_method',
        'amount',
        'service_booking_id',
        'notes',
        'cancellation_reason',
        'reminder_sent',
        'reminder_sent_at',
        'checked_in_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'checked_in_by',
        'branch_id',
        'insurance_provider_id',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'datetime',
        'amount' => 'decimal:2',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    // Scopes
    
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', today())
            ->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopeForProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    // Helper Methods
    
    public function isPast(): bool
    {
        return $this->appointment_date->isPast();
    }

    public function isToday(): bool
    {
        return $this->appointment_date->isToday();
    }

    public function canCheckIn(): bool
    {
        return $this->status === 'scheduled' || $this->status === 'confirmed';
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function checkIn($userId): void
    {
        $this->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);
    }

    public function cancel($reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function markNoShow(): void
    {
        $this->update([
            'status' => 'no_show',
        ]);
    }

    public function getFormattedDateTime(): string
    {
        return $this->appointment_date->format('d M Y') . ' at ' . 
               $this->appointment_time->format('H:i A');
    }
}