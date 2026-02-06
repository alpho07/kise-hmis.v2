<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCreditAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'branch_id',
        'account_number',
        'credit_limit',
        'current_balance',
        'available_credit',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'available_credit' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (!$account->account_number) {
                $account->account_number = 'CRD-' . date('Ymd') . '-' . str_pad(self::count() + 1, 5, '0', STR_PAD_LEFT);
            }
            $account->available_credit = $account->credit_limit - $account->current_balance;
        });

        static::updating(function ($account) {
            $account->available_credit = $account->credit_limit - $account->current_balance;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Check if account has available credit
     */
    public function hasAvailableCredit(float $amount): bool
    {
        return $this->available_credit >= $amount && $this->status === 'active';
    }

    /**
     * Charge amount to credit account
     */
    public function charge(float $amount, string $description, ?int $invoiceId = null): CreditTransaction
    {
        $balanceBefore = $this->current_balance;
        $this->current_balance += $amount;
        $this->save();

        return CreditTransaction::create([
            'credit_account_id' => $this->id,
            'invoice_id' => $invoiceId,
            'transaction_number' => 'CRT-' . date('Ymd') . '-' . str_pad(CreditTransaction::count() + 1, 5, '0', STR_PAD_LEFT),
            'type' => 'charge',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->current_balance,
            'description' => $description,
            'processed_by' => auth()->id(),
            'transaction_date' => now(),
        ]);
    }

    /**
     * Process payment against credit balance
     */
    public function processPayment(float $amount, string $description, ?int $paymentId = null): CreditTransaction
    {
        $balanceBefore = $this->current_balance;
        $this->current_balance = max(0, $this->current_balance - $amount);
        $this->save();

        return CreditTransaction::create([
            'credit_account_id' => $this->id,
            'payment_id' => $paymentId,
            'transaction_number' => 'CRT-' . date('Ymd') . '-' . str_pad(CreditTransaction::count() + 1, 5, '0', STR_PAD_LEFT),
            'type' => 'payment',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->current_balance,
            'description' => $description,
            'processed_by' => auth()->id(),
            'transaction_date' => now(),
        ]);
    }

    /**
     * Suspend credit account
     */
    public function suspend(string $reason): void
    {
        $this->update([
            'status' => 'suspended',
            'notes' => $reason,
        ]);
    }

    /**
     * Reactivate credit account
     */
    public function reactivate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function getAvailableCredit(): float
    {
        return $this->creditAccount->available_credit ?? 0;
    }

    /**
     * Get credit balance (amount owed)
     */
    public function getCreditBalance(): float
    {
        return $this->creditAccount->current_balance ?? 0;
    }

    /**
     * Check if client can use credit for amount
     */
    public function canUseCredit(float $amount): bool
    {
        if (!$this->hasActiveCreditAccount()) {
            return false;
        }

        return $this->creditAccount->hasAvailableCredit($amount);
    }
}
