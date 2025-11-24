<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_schema_id', 'version', 'schema_snapshot',
        'change_notes', 'created_by',
    ];

    protected $casts = [
        'schema_snapshot' => 'array',
    ];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormSchema::class, 'form_schema_id');
    }
}