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

    protected $table = 'assessment_form_responses';

    protected $fillable = [
        'assessment_form_schema_id',
        'client_id',
        'visit_id',
        'response_data',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormSchema::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}