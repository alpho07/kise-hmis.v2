<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'credit_transactions';

    protected $fillable = [
        'credit_account_id',
        'invoice_id',
        'payment_id',
        'transaction_number',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'processed_by',
        'transaction_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ClientCreditAccount::class, 'credit_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scopes
     */
    public function scopeCharges($query)
    {
        return $query->where('type', 'charge');
    }

    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('transaction_date', today());
    }

    /**
     * Check if transaction is a charge
     */
    public function isCharge(): bool
    {
        return $this->type === 'charge';
    }

    /**
     * Check if transaction is a payment
     */
    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }
}