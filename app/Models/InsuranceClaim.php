<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceClaim extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'insurance_claims';

    protected $fillable = [
        'claim_number',
        'invoice_id',
        'client_id',
        'visit_id',
        'branch_id',         // ✅ ADDED: Branch relationship
        'sponsor_type',
        'insurance_provider_id',
        'member_number',
        'claim_amount',
        'approved_amount',
        'paid_amount',
        'rejection_amount',
        'status',
        'service_date',      // ✅ CHANGED: Using service_date (matches database)
        'submitted_at',
        'approved_at',
        'payment_date',      // ✅ CHANGED: Using payment_date (matches database)
        'rejected_at',
        'payment_reference',
        'rejection_reason',
        'insurance_batch_invoice_id',  // ✅ CHANGED: Full column name
        'supporting_documents',
        'notes',            // ✅ ADDED: Notes column
        'internal_notes',   // ✅ ADDED: Internal notes
        'created_by',
        'submitted_by',
        'approved_by',
    ];

    protected $casts = [
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'rejection_amount' => 'decimal:2',
        'service_date' => 'date',        // ✅ CHANGED: service_date
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_date' => 'datetime',    // ✅ CHANGED: payment_date
        'rejected_at' => 'datetime',
        'supporting_documents' => 'array',
    ];

    /**
     * Boot method - auto-generate claim number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (!$claim->claim_number) {
                $claim->claim_number = static::generateClaimNumber($claim->sponsor_type ?? 'GEN');
            }
            
            if (!$claim->service_date) {
                $claim->service_date = now();
            }
        });
    }

    /**
     * Generate unique claim number
     */
    public static function generateClaimNumber(string $sponsorType): string
    {
        $prefix = strtoupper(substr($sponsorType, 0, 3));
        $yearMonth = now()->format('Ym');
        
        $lastClaim = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('sponsor_type', $sponsorType)
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastClaim ? intval(substr($lastClaim->claim_number, -5)) + 1 : 1;
        
        return "CLM-{$prefix}-{$yearMonth}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * ✅ ADDED: Branch relationship
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Alias for consistency with resources
     */
    public function provider(): BelongsTo
    {
        return $this->insuranceProvider();
    }

    public function batchInvoice(): BelongsTo
    {
        return $this->belongsTo(InsuranceBatchInvoice::class, 'insurance_batch_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClaimItem::class, 'claim_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Mark claim as submitted
     */
    public function markAsSubmitted(?int $userId = null): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Approve claim
     */
    public function approve(float $approvedAmount, ?int $userId = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(float $paidAmount, string $paymentRef): void
    {
        $this->update([
            'status' => 'paid',
            'paid_amount' => $paidAmount,
            'payment_date' => now(),
            'payment_reference' => $paymentRef,
        ]);
        
        // Update invoice sponsor claim status
        $this->invoice->update(['sponsor_claim_status' => 'paid']);
    }

    /**
     * Reject claim
     */
    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeBySponsor($query, string $sponsorType)
    {
        return $query->where('sponsor_type', $sponsorType);
    }

    public function scopeNotBatched($query)
    {
        return $query->whereNull('insurance_batch_invoice_id');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('service_date', [$startDate, $endDate]);
    }

    /**
     * Accessors
     */
    public function getSponsorNameAttribute(): string
    {
        return match($this->sponsor_type) {
            'sha' => 'Social Health Authority (SHA)',
            'ncpwd' => 'NCPWD',
            'insurance_private' => $this->insuranceProvider->name ?? 'Private Insurance',
            'waiver' => 'Waiver',
            default => strtoupper($this->sponsor_type),
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'submitted' => 'info',
            'approved' => 'primary',
            'paid' => 'success',
            'rejected' => 'danger',
            'partial' => 'warning',
            default => 'gray',
        };
    }
}