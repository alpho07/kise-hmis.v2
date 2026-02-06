<?php

namespace App\Enums;

enum BatchInvoiceStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case SENT = 'sent';
    case ACKNOWLEDGED = 'acknowledged';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::ACKNOWLEDGED => 'primary',
            self::PARTIALLY_PAID => 'purple',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
        };
    }
}