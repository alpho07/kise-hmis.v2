<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAllergy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_allergies';

    protected $fillable = [
        'client_id',
        'allergen_name',
        'allergy_type',
        'typical_reactions',
        'severity',
        'reaction',
        'notes',
    ];

    protected $casts = [
        'typical_reactions' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}