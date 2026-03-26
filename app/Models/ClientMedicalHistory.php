<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientMedicalHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_medical_history';

    protected $fillable = [
        'client_id',
        'previous_assessments',
        'developmental_concerns',
        'developmental_concerns_notes',
        'assistive_devices_history',
        'assistive_devices_notes',
        'medical_conditions',
        'current_medications',
        'surgical_history',
        'immunization_status',
        'feeding_history',
        'family_medical_history',
        'perinatal_history',
        'immunization_records',
    ];

    protected $casts = [
        'medical_conditions' => 'array',
        'previous_assessments' => 'array',
        'developmental_concerns' => 'array',
        'assistive_devices_history' => 'array',
        'feeding_history' => 'array',
        'perinatal_history' => 'array',
        'immunization_records' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}