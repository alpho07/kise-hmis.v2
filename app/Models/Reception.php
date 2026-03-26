<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reception extends Model
{
    use HasFactory;

    protected $table = 'claim_items';

    protected $fillable = [
        'claim_id',
        'invoice_item_id',
        'service_id',
        'service_name',
        'service_code',      // ✅ ADDED: Service code for external systems
        'quantity',
        'unit_price',
        'total_amount',
        'sponsor_percentage',
        'sponsor_amount',
        'client_amount',
        'claimed_amount',    // ✅ ADDED: Amount originally claimed
        'approved_amount',   // ✅ ADDED: Amount approved by sponsor
        'status',
        'rejection_reason',
        'notes',            // ✅ ADDED: Notes column
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sponsor_percentage' => 'decimal:2',
        'sponsor_amount' => 'decimal:2',
        'client_amount' => 'decimal:2',
        'claimed_amount' => 'decimal:2',    // ✅ ADDED
        'approved_amount' => 'decimal:2',   // ✅ ADDED
    ];

    /**
     * Relationships
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * ✅ ADDED: Check if fully approved
     */
    public function isFullyApproved(): bool
    {
        return $this->approved_amount >= $this->claimed_amount;
    }

    /**
     * ✅ ADDED: Get approval percentage
     */
    public function getApprovalPercentageAttribute(): float
    {
        if ($this->claimed_amount <= 0) {
            return 0;
        }
        
        return ($this->approved_amount / $this->claimed_amount) * 100;
    }

    /**
     * ✅ ADDED: Get rejected amount
     */
    public function getRejectedAmountAttribute(): float
    {
        return max(0, $this->claimed_amount - $this->approved_amount);
    }
}