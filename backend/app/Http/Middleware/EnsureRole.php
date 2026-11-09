<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** role:admin,seller — у пользователя есть хотя бы одна из ролей и токен служебный. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ! $user->hasRole(...array_map(fn ($r) => Role::from($r), $roles))) {
            abort(403, 'Недостаточно прав');
        }

        return $next($request);
    }
}
