<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Client extends Model
{
    use HasFactory, SoftDeletes, BelongsToBranch, LogsActivity;

    protected $fillable = [
        'branch_id',
        'uci',
        'client_type',
        'registration_source',
        'registration_date',
        
        // Personal Information
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'estimated_age',
        
        // Identifiers
        'ncpwd_number',
        'sha_number',
        'national_id',
        'birth_certificate_number',
        'passport_number',
        
        // Contact Information
        'phone_primary',
        'phone_secondary',
        'email',
        'preferred_communication',
        'consent_to_sms',
        
        // Guardian Information
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_national_id',
        
        // Address
        'county_id',
        'sub_county_id',
        'ward_id',
        'village',
        'primary_address',
        'landmark',
        
        // System
        'is_active',
        'photo',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'registration_date' => 'date',
        'is_active' => 'boolean',
        'consent_to_sms' => 'boolean',
        'estimated_age' => 'integer',
    ];

    protected $appends = [
        'full_name',
        'age',
    ];

    /**
     * Activity Log Configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'uci', 'first_name', 'last_name', 'gender', 'date_of_birth',
                'phone_primary', 'email', 'ncpwd_number', 'sha_number'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Relationships
     */
    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function subCounty()
    {
        return $this->belongsTo(SubCounty::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function insurances()
    {
        return $this->hasMany(ClientInsurance::class);
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function allergies()
    {
        return $this->hasMany(ClientAllergy::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        $name = trim($this->first_name . ' ' . ($this->middle_name ?? '') . ' ' . $this->last_name);
        return $name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->estimated_age;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeChildren($query)
    {
        return $query->where('estimated_age', '<', 18);
    }

    public function scopeAdults($query)
    {
        return $query->where('estimated_age', '>=', 18);
    }

    public function scopeByClientType($query, string $type)
    {
        return $query->where('client_type', $type);
    }

    public function scopeRegisteredBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('registration_date', [$startDate, $endDate]);
    }
}