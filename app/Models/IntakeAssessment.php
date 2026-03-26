<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class IntakeAssessment extends Model
{
    use HasFactory, BelongsToBranch;

    protected $table = 'intake_assessments';

    protected $fillable = [
        'visit_id',
        'client_id',
        'branch_id',
        'is_editable',
        'verification_mode',
        'data_verified',
        'verification_notes',
        'reason_for_visit',
        'previous_interventions',
        'current_concerns',
        'family_history',
        'developmental_history',
        'educational_background',
        'social_history',
        'services_required',
        'functional_screening_scores',
        'intake_summary',
        'assessment_summary',
        'recommendations',
        'assessed_by',
        'priority_level',
        'section_status',
        'is_finalized',
        'finalized_at',
    ];

    protected $casts = [
        'data_verified'               => 'boolean',
        'is_editable'                 => 'boolean',
        'service_recommendations'     => 'array',
        'referral_categories'         => 'array',
        'priority_level'              => 'integer',
        'section_status'              => 'array',
        'is_finalized'                => 'boolean',
        'finalized_at'                => 'datetime',
        'functional_screening_scores' => 'array',
        'services_required'           => 'array',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function functionalScreening(): HasOne
    {
        return $this->hasOne(FunctionalScreening::class);
    }
}