<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInsurancePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'insurance_provider_id',
        'covered_amount',
        'client_copay',
        'coverage_percentage',
        'is_fully_covered',
        'requires_preauthorization',
        'preauthorization_code',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'covered_amount' => 'decimal:2',
        'client_copay' => 'decimal:2',
        'coverage_percentage' => 'decimal:2',
        'is_fully_covered' => 'boolean',
        'requires_preauthorization' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the insurance provider
     */
    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Calculate total client pays
     */
    public function getClientPaysAttribute(): float
    {
        return $this->client_copay;
    }

    /**
     * Calculate insurance pays
     */
    public function getInsurancePaysAttribute(): float
    {
        return $this->covered_amount;
    }

    /**
     * Check if currently effective
     */
    public function isEffective(): bool
    {
        $now = today();
        
        $afterStart = !$this->effective_from || $this->effective_from <= $now;
        $beforeEnd = !$this->effective_to || $this->effective_to >= $now;
        
        return $this->is_active && $afterStart && $beforeEnd;
    }

    /**
     * Scope for active prices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for currently effective prices
     */
    public function scopeEffective($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', today());
            })
            ->where(function($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', today());
            });
    }
}