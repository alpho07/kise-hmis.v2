<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientSocioDemographic extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_socio_demographics';

    protected $fillable = [
        'client_id',
        'marital_status',
        'living_arrangement',
        'household_size',
        'source_of_support',
        'other_support_source',
        'primary_language',
        'other_languages',
        'socio_notes',
    ];

    protected $casts = [
        'household_size' => 'integer',
        'source_of_support' => 'array',
        'other_languages' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}