<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class VisitStage extends Model
{
    use HasFactory;

    protected $table = 'visit_stages';

    protected $fillable = [
        'visit_id',
        'stage',
        'started_at',
        'completed_at',
        'handled_by',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}