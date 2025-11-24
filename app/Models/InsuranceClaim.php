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

    protected $fillable = [
        'insurance_provider_id',
        'branch_id',
        'claim_number',
        'claim_date',
        'period_start',
        'period_end',
        'total_invoices',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (!$claim->claim_number) {
                $claim->claim_number = 'CLM-' . date('Ymd') . '-' . str_pad(self::count() + 1, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class);
    }

    /**
     * Submit claim for processing
     */
    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approve claim
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
    }

    /**
     * Reject claim
     */
    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'notes' => $reason,
        ]);
    }
}