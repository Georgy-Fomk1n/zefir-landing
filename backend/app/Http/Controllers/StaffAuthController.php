<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Presenters\UserPresenter;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\Auth\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Вход сотрудников (штат.зефир-и-пряник.рф). Саморегистрации нет: аккаунты создаёт администратор.
 * Для роли admin обязательна 2FA (TOTP): если она ещё не настроена, вход возвращает setup-секрет,
 * и токен выдаётся только после подтверждения кода.
 */
class StaffAuthController extends Controller
{
    public function __construct(private TokenIssuer $tokens) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower($request->string('email')->trim()))->first();
        if (! $user || ! $user->is_active || ! $user->isStaff() || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль']);
        }

        if ($user->hasRole(Role::Admin)) {
            if (! $user->hasTwoFactor()) {
                // Первый вход администратора: выдаём секрет для приложения-аутентификатора
                $secret = Cache::remember("2fa-setup:{$user->id}", 600, fn () => Totp::generateSecret());
                if (! $request->filled('code')) {
                    return response()->json(['twoFactor' => 'setup', 'secret' => $secret, 'otpauth' => Totp::uri($secret, $user->email)], 202);
                }
                if (! Totp::verify($secret, (string) $request->input('code'))) {
                    throw ValidationException::withMessages(['code' => 'Неверный код']);
                }
                $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
                Cache::forget("2fa-setup:{$user->id}");
            } else {
                if (! $request->filled('code')) {
                    return response()->json(['twoFactor' => 'required'], 202);
                }
                if (! Totp::verify($user->two_factor_secret, (string) $request->input('code'))) {
                    throw ValidationException::withMessages(['code' => 'Неверный код']);
                }
            }
        }

        return response()->json(['token' => $this->tokens->staff($user), 'user' => UserPresenter::make($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => UserPresenter::make($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
