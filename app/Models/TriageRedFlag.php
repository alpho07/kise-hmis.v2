<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TriageRedFlag extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'triage_red_flags';

    protected $fillable = [
        'triage_id',
        'flag_type',
        'description',
        'severity',
    ];

    protected $casts = [

    ];

    public function triage(): BelongsTo
    {
        return $this->belongsTo(Triage::class);
    }
}