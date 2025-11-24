<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, BelongsToBranch;

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'client_id',
        'amount_paid',
        'payment_method',
        'reference_number',
        'transaction_id',
        'status',
        'payment_date',
        'processed_by',
        'notes',
        'mpesa_receipt_number',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}