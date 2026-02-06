<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'school_type',
        'registration_number',
        'county_id',
        'sub_county_id',
        'location',
        'physical_address',
        'contact_person',
        'phone',
        'email',
        'website',
        'specializations',
        'boarding',
        'grades_offered',
        'capacity',
        'facilities',
        'support_services',
        'status',
        'notes',
    ];

    protected $casts = [
        'specializations' => 'array',
        'facilities' => 'array',
        'support_services' => 'array',
        'boarding' => 'boolean',
    ];

    // Relationships
    
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(SchoolPlacement::class);
    }

    public function activePlacements(): HasMany
    {
        return $this->hasMany(SchoolPlacement::class)->where('status', 'active');
    }

    // Scopes
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSpecial($query)
    {
        return $query->where('school_type', 'special');
    }

    public function scopeSupportsDisability($query, $disability)
    {
        return $query->whereJsonContains('specializations', $disability);
    }

    // Helper Methods
    
    public function supportsDisability($disability): bool
    {
        $specializations = $this->specializations ?? [];
        return in_array($disability, $specializations);
    }

    public function hasCapacity(): bool
    {
        if (!$this->capacity) {
            return true;
        }
        
        return $this->activePlacements()->count() < $this->capacity;
    }
}

class SchoolPlacement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'school_id',
        'placement_type',
        'program',
        'grade_level',
        'admission_date',
        'expected_completion_date',
        'status',
        'support_services',
        'academic_performance',
        'social_performance',
        'special_needs',
        'last_review_date',
        'review_notes',
        'placement_officer_id',
        'assessment_summary',
        'placement_letter_path',
        'end_date',
        'exit_reason',
        'exit_notes',
        'school_contact_person',
        'school_contact_phone',
    ];

    protected $casts = [
        'support_services' => 'array',
        'admission_date' => 'date',
        'expected_completion_date' => 'date',
        'last_review_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function placementOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placement_officer_id');
    }

    // Scopes
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helper Methods
    
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'end_date' => now(),
            'exit_reason' => 'completed',
        ]);
    }

    public function transfer($newSchoolId, $reason): void
    {
        $this->update([
            'status' => 'transferred',
            'end_date' => now(),
            'exit_reason' => 'transferred',
            'exit_notes' => $reason,
        ]);
    }

    public function discontinue($reason): void
    {
        $this->update([
            'status' => 'discontinued',
            'end_date' => now(),
            'exit_reason' => 'discontinued',
            'exit_notes' => $reason,
        ]);
    }
}