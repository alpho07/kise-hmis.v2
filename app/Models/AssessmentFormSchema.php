<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormSchema extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_form_schemas';

    protected $fillable = [
        'name',
        'description',
        'form_type',
        'schema',
        'is_active',
        'version',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentFormResponse::class);
    }
}