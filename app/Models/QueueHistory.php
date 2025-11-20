<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QueueHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'queue_history';

    protected $fillable = [
        'queue_id',
        'queue_entry_id',
        'client_id',
        'service_id',
        'queue_number',
        'wait_time_minutes',
        'service_time_minutes',
        'completed_at',
    ];

    protected $casts = [
        'queue_number' => 'integer',
        'wait_time_minutes' => 'integer',
        'service_time_minutes' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}