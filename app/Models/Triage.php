<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Triage extends Model
{
    use HasFactory, SoftDeletes,BelongsToBranch;

    protected $table = 'triages';

    protected $fillable = [
        'visit_id',
        'client_id',
        'temperature',
        'heart_rate',
        'respiratory_rate',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'oxygen_saturation',
        'weight',
        'height',
        'bmi',
        'pain_scale',
        'consciousness_level',
        'risk_level',
        'triage_status',
        'notes',
        'triaged_by',
        'cleared_by',
    'cleared_at',
    ];

    protected $casts = [
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'oxygen_saturation' => 'integer',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'pain_scale' => 'integer',
        'cleared_at' => 'datetime',

    ];

    public function clearedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'cleared_by');
}

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redFlags(): HasMany
    {
        return $this->hasMany(TriageRedFlag::class);
    }
}