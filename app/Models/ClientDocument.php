<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_documents';

    protected $fillable = [
        'client_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expiry_date' => 'date',
        'file_size' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}