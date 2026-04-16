<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToBranch;

    protected $fillable = [
        'visit_id',
        'client_id',
        'branch_id',
        'invoice_number',
        'subtotal',
        'covered_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_reason',
        'discount_approved_by',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'payment_pathway',
        'approved_at',
        'approved_by',
        'paid_at',
        'notes',
        'generated_by',
        'insurance_provider_id',
        'payment_administrator_id',
        'payment_notes',

        // Legacy aliases kept for backward compat with existing code
        'final_amount',
        'payment_method',
        'issued_by',
        'issued_at',
        'due_date',

        // Sponsor split columns (added by later migrations if present)
        'total_sponsor_amount',
        'total_client_amount',
        'amount_paid',
        'has_sponsor',
        'client_payment_status',
        'sponsor_claim_status',
        'billing_approved',
        'requires_cashier',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_date' => 'date',
        
        // ✅ ADDED: Casts for new columns
        'total_sponsor_amount' => 'decimal:2',
        'total_client_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'has_sponsor'      => 'boolean',
        'billing_approved' => 'boolean',
        'requires_cashier' => 'boolean',
    ];

    /**
     * Boot method to generate invoice number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 5, '0', STR_PAD_LEFT);
            }
            if (!$invoice->issued_at) {
                $invoice->issued_at = now();
            }
        });
    }

    public function paymentAdministrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_administrator_id');
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Get the visit
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who issued the invoice
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get all invoice items
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * ✅ ADDED: Get all payments for this invoice
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the payment (legacy - single payment)
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * ✅ ADDED: Get the insurance claim for this invoice
     */
    public function claim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class);
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $this->total_amount = $this->items->sum('subtotal');
        $this->final_amount = $this->total_amount + $this->tax_amount - $this->discount_amount;
        
        // ✅ ADDED: Calculate sponsor/client splits
        $this->total_sponsor_amount = $this->items->sum('sponsor_amount');
        $this->total_client_amount = $this->items->sum('client_amount');
        $this->has_sponsor = $this->total_sponsor_amount > 0;
        
        $this->save();
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);

        // Update all related service bookings
        foreach ($this->items as $item) {
            if ($item->serviceBooking) {
                $item->serviceBooking->update(['payment_status' => 'paid']);
            }
        }
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', today());
    }

    /**
     * Scope by payment method
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'final_amount', 'payment_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}