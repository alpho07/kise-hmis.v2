<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Support\Enums\MaxWidth;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}
