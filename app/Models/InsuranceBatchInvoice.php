<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceBatchInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'insurance_batch_invoices';

    protected $fillable = [
        'batch_number',
        'sponsor_type',
        'insurance_provider_id',
        'insurance_provider_name',
        'branch_id',                    // ✅ ADDED: Branch relationship
        'billing_period_start',         // ✅ FIXED: Was period_start
        'billing_period_end',           // ✅ FIXED: Was period_end
        'period_label',
        'total_claims',
        'total_amount',                 // ✅ FIXED: Was total_claim_amount
        'approved_amount',              // ✅ FIXED: Was total_approved_amount
        'paid_amount',                  // ✅ FIXED: Was total_paid_amount
        'rejected_amount',              // ✅ FIXED: Was total_rejected_amount
        'status',
        'generated_at',
        'sent_at',                      // ✅ ADDED: When sent to provider
        'acknowledged_at',              // ✅ ADDED: When acknowledged by provider
        'due_date',                     // ✅ ADDED: Payment due date
        'submitted_at',
        'approved_at',
        'paid_at',
        'pdf_path',
        'excel_path',
        'submission_reference',
        'notes',                        // ✅ ADDED: General notes
        'payment_notes',                // ✅ ADDED: Payment-specific notes
        'generated_by',
        'sent_by',                      // ✅ ADDED: Who sent the invoice
        'submitted_by',
    ];

    protected $casts = [
        'billing_period_start' => 'date',    // ✅ FIXED
        'billing_period_end' => 'date',      // ✅ FIXED
        'total_claims' => 'integer',
        'total_amount' => 'decimal:2',       // ✅ FIXED
        'approved_amount' => 'decimal:2',    // ✅ FIXED
        'paid_amount' => 'decimal:2',        // ✅ FIXED
        'rejected_amount' => 'decimal:2',    // ✅ FIXED
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'due_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($batch) {
            if (!$batch->batch_number) {
                $batch->batch_number = static::generateBatchNumber($batch->sponsor_type);
            }
            
            if (!$batch->generated_at) {
                $batch->generated_at = now();
            }
        });
    }

    /**
     * Generate unique batch number
     */
    public static function generateBatchNumber(string $sponsorType): string
    {
        $prefix = strtoupper(substr($sponsorType, 0, 3));
        $yearMonth = now()->format('Ym');
        
        $lastBatch = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('sponsor_type', $sponsorType)
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastBatch ? intval(substr($lastBatch->batch_number, -3)) + 1 : 1;
        
        return "BATCH-{$prefix}-{$yearMonth}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'insurance_batch_invoice_id');
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Alias for consistency
     */
    public function provider(): BelongsTo
    {
        return $this->insuranceProvider();
    }

    /**
     * ✅ ADDED: Branch relationship
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Calculate totals from claims
     */
    public function calculateTotals(): void
    {
        $claims = $this->claims;
        
        $this->update([
            'total_claims' => $claims->count(),
            'total_amount' => $claims->sum('claim_amount'),
            'approved_amount' => $claims->sum('approved_amount'),
            'paid_amount' => $claims->sum('paid_amount'),
            'rejected_amount' => $claims->sum('rejection_amount'),
        ]);
    }

    /**
     * Mark as submitted
     */
    public function markAsSubmitted(string $submissionRef, ?int $userId = null): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submission_reference' => $submissionRef,
            'submitted_by' => $userId ?? auth()->id(),
        ]);
        
        // Update all claims as submitted
        $this->claims()->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * ✅ ADDED: Mark as sent
     */
    public function markAsSent(?int $userId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(float $paidAmount): void
    {
        $this->update([
            'status' => 'paid',
            'paid_amount' => $paidAmount,
            'paid_at' => now(),
        ]);
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeBySponsor($query, string $sponsorType)
    {
        return $query->where('sponsor_type', $sponsorType);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->where('billing_period_start', '>=', $startDate)
            ->where('billing_period_end', '<=', $endDate);
    }

    /**
     * Accessors
     */
    public function getSponsorDisplayNameAttribute(): string
    {
        return match($this->sponsor_type) {
            'sha' => 'Social Health Authority (SHA)',
            'ncpwd' => 'NCPWD',
            'insurance_private' => $this->insurance_provider_name ?? 'Private Insurance',
            default => strtoupper($this->sponsor_type),
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'submitted' => 'info',
            'approved' => 'primary',
            'paid' => 'success',
            'partial' => 'warning',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /**
     * ✅ ADDED: Get balance (computed attribute)
     */
    public function getBalanceAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    /**
     * ✅ ADDED: Check if overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               $this->status !== 'paid';
    }
}