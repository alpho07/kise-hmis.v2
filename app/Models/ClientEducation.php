<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientEducation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_education';

    protected $fillable = [
        'client_id',
        'education_level',
        'school_type',
        'school_name',
        'grade_level',
        'currently_enrolled',
        'attendance_challenges',
        'attendance_notes',
        'performance_concern',
        'performance_notes',
        'employment_status',
        'employment_status_other',
        'occupation_type',
        'employer_name',
        'education_notes',
    ];

    protected $casts = [
        'currently_enrolled'   => 'boolean',
        'attendance_challenges' => 'boolean',
        'performance_concern'  => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}