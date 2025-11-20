<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemAlert extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_alerts';

    protected $fillable = [
        'alert_type',
        'severity',
        'title',
        'message',
        'target_user_id',
        'target_role',
        'is_read',
        'read_at',
        'action_url',
        'expires_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}