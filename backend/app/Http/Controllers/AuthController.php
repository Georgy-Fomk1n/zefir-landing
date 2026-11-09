<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Presenters\UserPresenter;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Вход покупателей (основной домен). Сотрудники входят через StaffAuthController. */
class AuthController extends Controller
{
    public function __construct(private TokenIssuer $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim(),
            'email' => mb_strtolower($request->string('email')->trim()),
            'phone' => $request->input('phone'),
            'password' => $request->input('password'),
            'marketing_consent' => $request->boolean('marketingConsent'),
            'pd_consent_at' => now(),
        ]);
        $user->addRole(Role::Customer);

        return response()->json(['token' => $this->tokens->customer($user), 'user' => UserPresenter::make($user)], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower($request->string('email')->trim()))->first();
        if (! $user || ! $user->is_active || $user->anonymized_at || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль']);
        }
        // Служебные аккаунты не входят в кабинет покупателя: у них отдельный вход на штат.зефир-и-пряник.рф
        if ($user->isStaff() && ! $user->hasRole(Role::Customer)) {
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль']);
        }

        return response()->json(['token' => $this->tokens->customer($user), 'user' => UserPresenter::make($user)]);
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
