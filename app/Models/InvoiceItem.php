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
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'insurance_provider_id',
        'insurance_covered_amount',
        'client_copay_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'insurance_covered_amount' => 'decimal:2',
    'client_copay_amount' => 'decimal:2',
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
        });

        static::updating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
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
}