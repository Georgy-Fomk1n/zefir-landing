<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Токены Sanctum двух непересекающихся видов:
 *  • customer — для основного домена (ability «customer»), длинный срок;
 *  • staff — для штат.зефир-и-пряник.рф (ability «staff» + роли), короткий срок, принудительный повторный вход.
 * Маршруты кабинета покупателя требуют ability customer, служебные — staff, поэтому токены не взаимозаменяемы.
 */
class TokenIssuer
{
    public function customer(User $user, string $device = 'web'): string
    {
        return $user->createToken("customer:{$device}", ['customer'], now()->addMinutes(config('shop.tokens.customer_ttl')))->plainTextToken;
    }

    public function staff(User $user): string
    {
        $abilities = array_merge(['staff'], array_map(fn ($r) => "role:{$r}", $user->roles()));
        // Один активный служебный сеанс на сотрудника
        $user->tokens()->where('name', 'staff')->delete();

        return $user->createToken('staff', $abilities, now()->addMinutes(config('shop.tokens.staff_ttl')))->plainTextToken;
    }
}
