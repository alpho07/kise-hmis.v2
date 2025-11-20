<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrisisIncident extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crisis_incidents';

    protected $fillable = [
        'visit_id',
        'client_id',
        'incident_type',
        'severity',
        'description',
        'actions_taken',
        'reported_by',
        'reported_at',
        'resolved_at',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}