<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInsurance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'insurance_provider_id',
        'membership_number',
        'policy_number',
        'principal_member_name',
        'principal_member_id',
        'relationship_to_principal',
        'valid_from',
        'valid_to',
        'is_primary',
        'is_active',
        'verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'coverage_details',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'coverage_details' => 'array',
    ];

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the insurance provider
     */
    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    /**
     * Get the user who verified
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Check if insurance is currently valid
     */
    public function isValid(): bool
    {
        $now = today();
        
        $afterStart = !$this->valid_from || $this->valid_from <= $now;
        $beforeEnd = !$this->valid_to || $this->valid_to >= $now;
        
        return $this->is_active && $this->verified && $afterStart && $beforeEnd;
    }

    /**
     * Verify insurance
     */
    public function verify(User $user, ?string $notes = null): void
    {
        $this->update([
            'verified' => true,
            'verified_by' => $user->id,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Scope for active insurance
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified insurance
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope for primary insurance
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for currently valid insurance
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('verified', true)
            ->where(function($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', today());
            })
            ->where(function($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', today());
            });
    }
}