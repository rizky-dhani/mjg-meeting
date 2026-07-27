<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getHeading(): string
    {
        return 'Medquest Meeting Booking';
    }
}
