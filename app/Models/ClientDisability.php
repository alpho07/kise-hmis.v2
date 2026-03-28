<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientDisability extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_disabilities';

    protected $fillable = [
        'client_id',
        'is_disability_known',
        'disability_categories',
        'onset',
        'level_of_functioning',
        'assistive_technology',
        'assistive_technology_notes',
        'disability_notes',
        'evidence_files',
        'ncpwd_registered',
        'ncpwd_verification_status',
    ];

    protected $casts = [
        'is_disability_known' => 'boolean',
        'disability_categories' => 'array',
        'assistive_technology' => 'array',
        'evidence_files' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}