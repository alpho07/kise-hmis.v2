<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';
    case MPESA = 'mpesa';
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_ACCOUNT = 'credit_account';
    case SHA = 'sha';
    case NCPWD = 'ncpwd';
    case WAIVER = 'waiver';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::MPESA => 'M-PESA',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CREDIT_ACCOUNT => 'Credit Account',
            self::SHA => 'SHA (Social Health Authority)',
            self::NCPWD => 'NCPWD',
            self::WAIVER => 'Waiver',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CASH => 'heroicon-o-banknotes',
            self::MPESA => 'heroicon-o-device-phone-mobile',
            self::BANK_TRANSFER => 'heroicon-o-building-library',
            self::CREDIT_ACCOUNT => 'heroicon-o-credit-card',
            self::SHA => 'heroicon-o-shield-check',
            self::NCPWD => 'heroicon-o-user-group',
            self::WAIVER => 'heroicon-o-hand-raised',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CASH => 'success',
            self::MPESA => 'info',
            self::BANK_TRANSFER => 'primary',
            self::CREDIT_ACCOUNT => 'warning',
            self::SHA => 'purple',
            self::NCPWD => 'teal',
            self::WAIVER => 'gray',
        };
    }

    public function isSponsor(): bool
    {
        return in_array($this, [self::SHA, self::NCPWD, self::WAIVER]);
    }
}