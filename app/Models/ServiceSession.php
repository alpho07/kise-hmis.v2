<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_sessions';

    protected $fillable = [
        'visit_id',
        'service_booking_id',
        'service_id',
        'client_id',
        'provider_id',
        'session_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'session_type',
        'status',
        'attendance_status',
        'signed_in_by',
        'baseline_assessment_data',
        'intervention_plan',
    ];

    protected $casts = [
        'session_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'baseline_assessment_data' => 'array',
    ];

    public function signedInBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'signed_in_by');
}

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ServiceNote::class);
    }
}