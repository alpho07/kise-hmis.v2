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
        'marital_status_other',
        'living_arrangement',
        'living_arrangement_other',
        'household_size',
        'primary_caregiver',
        'source_of_support',
        'other_support_source',
        'school_enrolled',
        'primary_language',
        'other_languages',
        'accessibility_at_home',
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