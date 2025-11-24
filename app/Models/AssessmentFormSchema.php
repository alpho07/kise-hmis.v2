<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormSchema extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'version', 'category', 'description',
        'schema', 'conditional_rules', 'validation_rules', 'auto_referrals',
        'estimated_minutes', 'allow_draft', 'allow_partial_submission',
        'is_active', 'is_published', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'schema' => 'array',
        'conditional_rules' => 'array',
        'validation_rules' => 'array',
        'auto_referrals' => 'array',
        'allow_draft' => 'boolean',
        'allow_partial_submission' => 'boolean',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentFormResponse::class, 'form_schema_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentFormVersion::class, 'form_schema_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
