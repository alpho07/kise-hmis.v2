<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // ─── Age Group ───────────────────────────────────────────────────────────────
    // WHO the service is for.  Used at intake to surface only age-appropriate
    // options and in billing to auto-select the correct price tier.

    public const AGE_GROUP_CHILD = 'child';   // clients < 18
    public const AGE_GROUP_ADULT = 'adult';   // clients ≥ 18
    public const AGE_GROUP_ALL   = 'all';     // any age (e.g. Ear Molds)

    /** @return array<string, string>  value => label */
    public static function ageGroupOptions(): array
    {
        return [
            self::AGE_GROUP_CHILD => 'Child (< 18 yrs)',
            self::AGE_GROUP_ADULT => 'Adult (≥ 18 yrs)',
            self::AGE_GROUP_ALL   => 'All Ages',
        ];
    }

    // ─── Business Category ───────────────────────────────────────────────────────
    // WHAT kind of service it is.  Orthogonal to age_group.

    public const CATEGORY_ASSESSMENT         = 'Assessment';
    public const CATEGORY_THERAPY            = 'Therapy';
    public const CATEGORY_COUNSELING         = 'Counseling';
    public const CATEGORY_CONSULTATION       = 'Consultation';
    public const CATEGORY_ASSISTIVE_TECH     = 'Assistive Technology';

    /** @return array<string, string> */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_ASSESSMENT     => 'Assessment',
            self::CATEGORY_THERAPY        => 'Therapy',
            self::CATEGORY_COUNSELING     => 'Counseling',
            self::CATEGORY_CONSULTATION   => 'Consultation',
            self::CATEGORY_ASSISTIVE_TECH => 'Assistive Technology',
        ];
    }

    // ─── Service Type ────────────────────────────────────────────────────────────

    public const TYPE_ASSESSMENT         = 'assessment';
    public const TYPE_THERAPY            = 'therapy';
    public const TYPE_CONSULTATION       = 'consultation';
    public const TYPE_ASSISTIVE_TECH     = 'assistive_technology';
    public const TYPE_SCREENING          = 'screening';

    /** @return array<string, string> */
    public static function serviceTypeOptions(): array
    {
        return [
            self::TYPE_ASSESSMENT     => 'Assessment',
            self::TYPE_THERAPY        => 'Therapy',
            self::TYPE_CONSULTATION   => 'Consultation',
            self::TYPE_ASSISTIVE_TECH => 'Assistive Technology',
            self::TYPE_SCREENING      => 'Screening',
        ];
    }

    // ─── Fillable ────────────────────────────────────────────────────────────────

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
        'age_group',
        'subcategory',
        'notes',
        'service_type',
        'requires_sessions',
        'default_session_count',
    ];

    // ─── Casts ───────────────────────────────────────────────────────────────────

    protected $casts = [
        'base_price'          => 'decimal:2',
        'sha_price'           => 'decimal:2',
        'ncpwd_price'         => 'decimal:2',
        'requires_assessment' => 'boolean',
        'is_recurring'        => 'boolean',
        'is_active'           => 'boolean',
        'sha_covered'         => 'boolean',
        'ncpwd_covered'       => 'boolean',
        'duration_minutes'    => 'integer',
        'available_from'      => 'date',
        'available_until'     => 'date',
        'requires_sessions'   => 'boolean',
        'default_session_count' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────────────────────

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

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function assessmentForms(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentFormSchema::class,
            'service_form_schemas'
        );
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Filter services appropriate for a specific age group.
     * Includes records explicitly targeting that group AND records marked 'all'.
     *
     * @param  string  $ageGroup  self::AGE_GROUP_CHILD | AGE_GROUP_ADULT | AGE_GROUP_ALL
     */
    public function scopeForAgeGroup(Builder $query, string $ageGroup): Builder
    {
        if ($ageGroup === self::AGE_GROUP_ALL) {
            return $query;
        }

        return $query->whereIn('age_group', [$ageGroup, self::AGE_GROUP_ALL]);
    }

    /**
     * Filter services appropriate for a given Client, based on their age.
     * Resolves age from estimated_age falling back to date_of_birth.
     */
    public function scopeForClient(Builder $query, Client $client): Builder
    {
        $age = $client->estimated_age
            ?? ($client->date_of_birth ? $client->date_of_birth->age : null);

        if ($age === null) {
            // Age unknown — return all services so the intake officer can choose
            return $query;
        }

        $ageGroup = $age < 18 ? self::AGE_GROUP_CHILD : self::AGE_GROUP_ADULT;

        return $query->whereIn('age_group', [$ageGroup, self::AGE_GROUP_ALL]);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByServiceType(Builder $query, string $type): Builder
    {
        return $query->where('service_type', $type);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    public function isChildService(): bool
    {
        return $this->age_group === self::AGE_GROUP_CHILD;
    }

    public function isAdultService(): bool
    {
        return $this->age_group === self::AGE_GROUP_ADULT;
    }

    public function isAvailableForAge(?int $age): bool
    {
        if ($age === null) return true;

        return match ($this->age_group) {
            self::AGE_GROUP_CHILD => $age < 18,
            self::AGE_GROUP_ADULT => $age >= 18,
            default               => true,
        };
    }

    public function isAvailableForClient(Client $client): bool
    {
        $age = $client->estimated_age
            ?? ($client->date_of_birth ? $client->date_of_birth->age : null);

        return $this->isAvailableForAge($age);
    }

    // ─── Pricing ─────────────────────────────────────────────────────────────────

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

        return (float) $this->base_price;
    }

    public function isCoveredBy(?InsuranceProvider $insurance = null): bool
    {
        if (!$insurance) return false;

        return $this->insurancePrices()
            ->where('insurance_provider_id', $insurance->id)
            ->active()
            ->exists();
    }

    // ─── Mutators ────────────────────────────────────────────────────────────────

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    // ─── Activity Logging ────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'code',
                'base_price',
                'is_active',
                'age_group',
                'category',
                'service_type',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
