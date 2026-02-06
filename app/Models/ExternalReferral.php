<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalReferral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'client_id',
        'referring_provider_id',
        'from_department_id',
        'facility_name',
        'facility_type',
        'department_specialty',
        'facility_contact',
        'facility_email',
        'urgency',
        'status',
        'reason',
        'clinical_summary',
        'investigations_done',
        'current_management',
        'specific_request',
        'preferred_contact',
        'alternative_contact',
        'referral_letter_path',
        'supporting_documents',
        'appointment_date',
        'appointment_time',
        'feedback',
        'feedback_documents',
        'referred_at',
        'appointment_confirmed_at',
        'attended_at',
        'feedback_received_at',
    ];

    protected $casts = [
        'supporting_documents' => 'array',
        'feedback_documents' => 'array',
        'appointment_date' => 'date',
        'appointment_time' => 'datetime',
        'referred_at' => 'datetime',
        'appointment_confirmed_at' => 'datetime',
        'attended_at' => 'datetime',
        'feedback_received_at' => 'datetime',
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

    public function referringProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_provider_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    // Scopes
    
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['sent', 'appointment_scheduled']);
    }

    public function scopePendingFeedback($query)
    {
        return $query->where('status', 'attended')
            ->whereNull('feedback_received_at');
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('urgency', ['urgent', 'emergency']);
    }

    // Helper Methods
    
    public function isActive(): bool
    {
        return in_array($this->status, ['sent', 'appointment_scheduled']);
    }

    public function scheduleAppointment($date, $time): void
    {
        $this->update([
            'status' => 'appointment_scheduled',
            'appointment_date' => $date,
            'appointment_time' => $time,
            'appointment_confirmed_at' => now(),
        ]);
    }

    public function markAttended(): void
    {
        $this->update([
            'status' => 'attended',
            'attended_at' => now(),
        ]);
    }

    public function addFeedback($feedback, $documents = null): void
    {
        $this->update([
            'status' => 'completed',
            'feedback' => $feedback,
            'feedback_documents' => $documents,
            'feedback_received_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }
}