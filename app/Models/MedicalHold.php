<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalHold extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'medical_holds';

    protected $fillable = [
        'visit_id',
        'client_id',
        'hold_reason',
        'medical_notes',
        'placed_by',
        'placed_at',
        'cleared_by',
        'cleared_at',
        'status',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}