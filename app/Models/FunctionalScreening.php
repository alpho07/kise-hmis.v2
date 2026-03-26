<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunctionalScreening extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'functional_screenings';

    protected $fillable = [
        'intake_assessment_id',
        'client_id',
        'age_band',
        'screening_answers',
        'overall_summary',
        'mobility_score',
        'self_care_score',
        'communication_score',
        'cognition_score',
        'social_interaction_score',
        'emotional_regulation_score',
        'sensory_processing_score',
        'activities_daily_living_score',
        'total_score',
        'screening_notes',
    ];

    protected $casts = [
        'screening_answers'          => 'array',
        'mobility_score'             => 'integer',
        'self_care_score'            => 'integer',
        'communication_score'        => 'integer',
        'cognition_score'            => 'integer',
        'social_interaction_score'   => 'integer',
        'emotional_regulation_score' => 'integer',
        'sensory_processing_score'   => 'integer',
        'activities_daily_living_score' => 'integer',
        'total_score'                => 'integer',
    ];

    public function intakeAssessment(): BelongsTo
    {
        return $this->belongsTo(IntakeAssessment::class);
    }
}