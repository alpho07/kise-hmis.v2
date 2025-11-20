<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataSyncLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'data_sync_logs';

    protected $fillable = [
        'sync_type',
        'source',
        'records_processed',
        'records_success',
        'records_failed',
        'started_at',
        'completed_at',
        'status',
        'error_log',
    ];

    protected $casts = [
        'records_processed' => 'integer',
        'records_success' => 'integer',
        'records_failed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_log' => 'array',
    ];


}