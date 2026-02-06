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
        'visit_id',          // ✅ ADDED: Visit relationship
        'client_id',
        'amount_paid',
        'payment_method',
        'reference_number',
        'transaction_id',
        'status',
        'payment_date',
        'processed_by',
        'notes',
        
        // M-PESA
        'mpesa_receipt_number',
        'mpesa_phone_number',
        
        // HYBRID PAYMENTS
        'is_split_payment',
        'split_details',
        
        // ACCOUNT CREDITS
        'credit_account_id',
        'credit_transaction_id',
        'account_credit_used',
        
        // CARD PAYMENTS
        'card_last_four',
        'card_type',
        'card_approval_code',
        
        // BANK TRANSFER
        'bank_reference',
        'bank_name',
        
        // CHANGE
        'change_given',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'datetime',
        'is_split_payment' => 'boolean',
        'split_details' => 'array',
        'account_credit_used' => 'decimal:2',
        'change_given' => 'decimal:2',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($payment) {
            // If credit was used, create credit transaction
            if ($payment->account_credit_used > 0 && $payment->credit_account_id) {
                $creditAccount = ClientCreditAccount::find($payment->credit_account_id);
                if ($creditAccount) {
                    $transaction = $creditAccount->processPayment(
                        $payment->account_credit_used,
                        "Payment for Invoice #{$payment->invoice->invoice_number}",
                        $payment->id
                    );
                    
                    // Link transaction to payment
                    $payment->update(['credit_transaction_id' => $transaction->id]);
                }
            }
        });
    }

    /**
     * Relationships
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * ✅ ADDED: Get the visit
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ClientCreditAccount::class, 'credit_account_id');
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'credit_transaction_id');
    }

    /**
     * Get payment breakdown for display
     */
    public function getPaymentBreakdown(): array
    {
        $breakdown = [];

        if ($this->is_split_payment && $this->split_details) {
            $breakdown = $this->split_details;
        } else {
            $breakdown[] = [
                'method' => $this->payment_method,
                'amount' => $this->amount_paid,
                'reference' => $this->getReferenceForMethod($this->payment_method),
            ];
        }

        // Add credit as separate line item if used
        if ($this->account_credit_used > 0) {
            $breakdown[] = [
                'method' => 'account_credit',
                'amount' => $this->account_credit_used,
                'reference' => $this->creditAccount->account_number ?? null,
            ];
        }

        return $breakdown;
    }

    /**
     * Get reference number for specific payment method
     */
    protected function getReferenceForMethod(string $method): ?string
    {
        return match($method) {
            'mpesa' => $this->mpesa_receipt_number,
            'card' => $this->card_approval_code,
            'bank_transfer' => $this->bank_reference,
            default => $this->transaction_id,
        };
    }

    /**
     * Check if account credit was used
     */
    public function usedAccountCredit(): bool
    {
        return $this->account_credit_used > 0;
    }

    /**
     * Get total amount including credit
     */
    public function getTotalWithCredit(): float
    {
        return $this->amount_paid + ($this->account_credit_used ?? 0);
    }

    /**
     * Get remaining credit balance after this payment
     */
    public function getRemainingCreditBalance(): ?float
    {
        if ($this->creditTransaction) {
            return $this->creditTransaction->balance_after;
        }
        
        if ($this->creditAccount) {
            return $this->creditAccount->available_credit;
        }

        return null;
    }

    /**
     * Format payment methods for display
     */
    public function getPaymentMethodsDisplay(): string
    {
        if (!$this->is_split_payment && !$this->usedAccountCredit()) {
            return match($this->payment_method) {
                'cash' => 'Cash',
                'mpesa' => 'M-PESA',
                'card' => 'Card',
                'bank_transfer' => 'Bank Transfer',
                'account_credit' => 'Account Credit',
                default => ucfirst($this->payment_method ?? 'Unknown'),
            };
        }

        $methods = [];

        if ($this->is_split_payment && $this->split_details) {
            foreach ($this->split_details as $detail) {
                $methods[] = match($detail['method']) {
                    'cash' => 'Cash',
                    'mpesa' => 'M-PESA',
                    'card' => 'Card',
                    default => ucfirst($detail['method']),
                };
            }
        } else {
            $methods[] = match($this->payment_method) {
                'cash' => 'Cash',
                'mpesa' => 'M-PESA',
                'card' => 'Card',
                default => ucfirst($this->payment_method ?? 'Cash'),
            };
        }

        if ($this->usedAccountCredit()) {
            $methods[] = 'Credit';
        }

        return implode(' + ', $methods);
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeSplitPayments($query)
    {
        return $query->where('is_split_payment', true);
    }

    public function scopeUsedCredit($query)
    {
        return $query->where('account_credit_used', '>', 0);
    }
}