<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentAutoReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_response_id', 'client_id', 'visit_id',
        'service_point', 'department', 'priority', 'reason',
        'trigger_data', 'status', 'acknowledged_at', 'acknowledged_by',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormResponse::class, 'form_response_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByService($query, string $servicePoint)
    {
        return $query->where('service_point', $servicePoint);
    }
}