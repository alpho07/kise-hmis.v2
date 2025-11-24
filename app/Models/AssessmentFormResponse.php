<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;


class AssessmentFormResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'form_schema_id', 'visit_id', 'client_id', 'branch_id',
        'response_data', 'metadata', 'status', 'completion_percentage',
        'started_at', 'completed_at', 'submitted_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'response_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormSchema::class, 'form_schema_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autoReferrals(): HasMany
    {
        return $this->hasMany(AssessmentAutoReferral::class, 'form_response_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByForm($query, $formSchemaId)
    {
        return $query->where('form_schema_id', $formSchemaId);
    }
}
