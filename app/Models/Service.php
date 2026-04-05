<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Service extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * CATEGORY ENUM
     */
    public const CATEGORY_CHILD = 'child';
    public const CATEGORY_ADULT = 'adult';
    public const CATEGORY_BOTH  = 'both';

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_CHILD => 'Child',
            self::CATEGORY_ADULT => 'Adult',
            self::CATEGORY_BOTH  => 'Both',
        ];
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'department_id',
        'base_price',
        'sha_covered',
        'sha_price',
        'ncpwd_covered',
        'ncpwd_price',
        'requires_assessment',
        'is_recurring',
        'duration_minutes',
        'is_active',
        'available_from',
        'available_until',
        'category',
        'subcategory',
        'notes',
        'service_type',
        'requires_sessions',
        'default_session_count',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sha_price' => 'decimal:2',
        'ncpwd_price' => 'decimal:2',
        'requires_assessment' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'sha_covered' => 'boolean',
        'ncpwd_covered' => 'boolean',
        'duration_minutes' => 'integer',
        'available_from' => 'date',
        'available_until' => 'date',
        'requires_sessions' => 'boolean',
        'default_session_count' => 'integer',
    ];

    /**
     * RELATIONSHIPS
     */

    /**
     * Get all service requests for this service
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * Get pending service requests
     */
    public function pendingRequests()
    {
        return $this->serviceRequests()
            ->where('status', 'pending_payment');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    public function insurancePrices(): HasMany
    {
        return $this->hasMany(ServiceInsurancePrice::class);
    }

    public function assessmentForms(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentFormSchema::class,
            'service_form_schemas'
        );
    }

    /**
     * PRICE HANDLING
     */
    public function getPriceForInsurance(InsuranceProvider $insurance): ?ServiceInsurancePrice
    {
        return $this->insurancePrices()
            ->where('insurance_provider_id', $insurance->id)
            ->active()
            ->first();
    }

    public function getPriceForMethod(?InsuranceProvider $insurance = null): float
    {
        if ($insurance) {
            $insurancePrice = $this->getPriceForInsurance($insurance);
            if ($insurancePrice) {
                return $insurancePrice->client_copay;
            }
        }

        return $this->base_price;
    }

    public function isCoveredBy(?InsuranceProvider $insurance = null): bool
    {
        if (!$insurance) {
            return false;
        }

        return $this->insurancePrices()
            ->where('insurance_provider_id', $insurance->id)
            ->active()
            ->exists();
    }

    /**
     * SCOPES
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeChild($query)
    {
        return $query->where('category', self::CATEGORY_CHILD);
    }

    public function scopeAdult($query)
    {
        return $query->where('category', self::CATEGORY_ADULT);
    }

    public function scopeBoth($query)
    {
        return $query->where('category', self::CATEGORY_BOTH);
    }

    /**
     * ACCESSORS & MUTATORS
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtoupper($value);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    /**
     * SPATIE ACTIVITY LOGGING
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'code',
                'base_price',
                'is_active',
                'category',
                'service_type',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

}
