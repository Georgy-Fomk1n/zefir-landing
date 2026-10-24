<?php

namespace App\Http\Presenters;

use App\Models\User;

class UserPresenter
{
    public static function make(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'roles' => $u->roles(),
            'marketingConsent' => $u->marketing_consent,
            'twoFactorEnabled' => $u->hasTwoFactor(),
        ];
    }
}
