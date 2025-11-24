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
    use HasFactory, SoftDeletes, LogsActivity,BelongsToBranch;

    protected $fillable = [
        'visit_id',
        'client_id',
        'invoice_number',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'final_amount',
        'payment_method',
        'status',
        'issued_by',
        'issued_at',
        'due_date',
        'notes',
        'payment_administrator_id',
    'payment_notes',
    'insurance_provider_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_date' => 'date',
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
     * Get the payment
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $this->total_amount = $this->items->sum('subtotal');
        $this->final_amount = $this->total_amount + $this->tax_amount - $this->discount_amount;
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