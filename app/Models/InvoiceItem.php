<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'service_booking_id',
        'service_id',
        'department_id',
        'item_description',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'discount_amount',
        'discount',
        'covered_amount',
        'total',
        'tax_amount',
        'insurance_provider_id',
        'insurance_covered_amount',
        'client_copay_amount',

        // Sponsor/Client split columns (added by later migrations if present)
        'sponsor_type',
        'sponsor_percentage',
        'sponsor_amount',
        'client_amount',
        'client_payment_status',
        'sponsor_claim_status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'insurance_covered_amount' => 'decimal:2',
        'client_copay_amount' => 'decimal:2',
        
        // ✅ ADDED: Casts for split columns
        'sponsor_percentage' => 'decimal:2',
        'sponsor_amount' => 'decimal:2',
        'client_amount' => 'decimal:2',
    ];

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Boot method to calculate subtotal
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->subtotal) {
                $item->subtotal = $item->quantity * $item->unit_price;
            }
            
            // ✅ ADDED: Auto-calculate sponsor/client split if not set
            if ($item->sponsor_percentage && !$item->sponsor_amount) {
                $item->sponsor_amount = ($item->subtotal * $item->sponsor_percentage) / 100;
                $item->client_amount = $item->subtotal - $item->sponsor_amount;
            }
        });

        static::updating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
            
            // ✅ ADDED: Recalculate split on update
            if ($item->sponsor_percentage) {
                $item->sponsor_amount = ($item->subtotal * $item->sponsor_percentage) / 100;
                $item->client_amount = $item->subtotal - $item->sponsor_amount;
            }
        });
    }

    /**
     * Get the invoice
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the service booking (CRITICAL LINK)
     */
    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    /**
     * Get the service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * ✅ ADDED: Calculate total amount (subtotal - discount + tax)
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->subtotal - ($this->discount_amount ?? 0) + ($this->tax_amount ?? 0);
    }
}