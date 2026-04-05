<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InsuranceProvider extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'type',
        'description',
        'contact_person',
        'phone',
        'email',
        'address',
        'claim_submission_method',
        'claim_email',
        'claim_portal_url',
        'default_coverage_percentage',
        'coverage_limits',
        'excluded_services',
        'claim_processing_days',
        'requires_preauthorization',
        'requires_referral',
        'is_active',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'default_coverage_percentage' => 'decimal:2',
        'coverage_limits' => 'array',
        'excluded_services' => 'array',
        'claim_processing_days' => 'integer',
        'requires_preauthorization' => 'boolean',
        'requires_referral' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Get all service prices for this insurance
     */
    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServiceInsurancePrice::class);
    }

    /**
     * Get all clients with this insurance
     */
    public function clientInsurances(): HasMany
    {
        return $this->hasMany(ClientInsurance::class);
    }

    /**
     * Get all invoices using this insurance
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all service bookings using this insurance
     */
    public function serviceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    /**
     * Check if service is covered
     */
    public function coversService(Service $service): bool
    {
        if ($this->excluded_services && in_array($service->id, $this->excluded_services)) {
            return false;
        }

        return $this->servicePrices()
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get price for service
     */
    public function getPriceForService(Service $service): ?ServiceInsurancePrice
    {
        return $this->servicePrices()
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', today());
            })
            ->where(function($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', today());
            })
            ->first();
    }

    /**
     * Scope for active insurance providers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for government scheme insurance
     */
    public function scopeGovernmentScheme($query)
    {
        return $query->where('type', 'government_scheme');
    }

    /**
     * Scope for eCitizen insurance
     */
    public function scopeEcitizen($query)
    {
        return $query->where('type', 'ecitizen');
    }

    /**
     * Scope for private insurance
     */
    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active', 'default_coverage_percentage'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}