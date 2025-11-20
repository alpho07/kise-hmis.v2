<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_notes';

    protected $fillable = [
        'service_session_id',
        'note_type',
        'content',
        'is_confidential',
        'created_by',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
    ];

    public function serviceSession(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}