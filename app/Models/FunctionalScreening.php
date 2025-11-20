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
        'mobility_score',
        'self_care_score',
        'communication_score',
        'cognition_score',
        'social_interaction_score',
        'sensory_score',
        'behavior_score',
        'adaptive_skills_score',
        'total_score',
        'screening_notes',
    ];

    protected $casts = [
        'mobility_score' => 'integer',
        'self_care_score' => 'integer',
        'communication_score' => 'integer',
        'cognition_score' => 'integer',
        'social_interaction_score' => 'integer',
        'sensory_score' => 'integer',
        'behavior_score' => 'integer',
        'adaptive_skills_score' => 'integer',
        'total_score' => 'integer',
    ];

    public function intakeAssessment(): BelongsTo
    {
        return $this->belongsTo(IntakeAssessment::class);
    }
}