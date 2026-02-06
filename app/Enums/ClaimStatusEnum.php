<?php

namespace App\Enums;

enum ClaimStatusEnum: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending Submission',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::REJECTED => 'Rejected',
            self::DISPUTED => 'Disputed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'gray',
            self::SUBMITTED => 'info',
            self::UNDER_REVIEW => 'warning',
            self::APPROVED => 'success',
            self::PARTIALLY_PAID => 'purple',
            self::PAID => 'success',
            self::REJECTED => 'danger',
            self::DISPUTED => 'orange',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-o-clock',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::UNDER_REVIEW => 'heroicon-o-magnifying-glass',
            self::APPROVED => 'heroicon-o-check-circle',
            self::PARTIALLY_PAID => 'heroicon-o-banknotes',
            self::PAID => 'heroicon-o-check-badge',
            self::REJECTED => 'heroicon-o-x-circle',
            self::DISPUTED => 'heroicon-o-exclamation-triangle',
        };
    }
}