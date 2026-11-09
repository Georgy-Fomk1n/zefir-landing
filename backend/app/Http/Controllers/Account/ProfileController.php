<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Presenters\UserPresenter;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[78][\d\s()-]{9,}$/'],
            'marketingConsent' => ['boolean'],
        ]);
        $request->user()->update(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'marketing_consent' => (bool) ($data['marketingConsent'] ?? false)]);

        return response()->json(['data' => UserPresenter::make($request->user())]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = $request->user();
        $user->update(['password' => $data['password']]);
        // Остальные сеансы завершаются
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(null, 204);
    }

    /**
     * Удаление аккаунта (152-ФЗ): ПДн обезличиваются, токены и карты удаляются, абонементы отменяются.
     * Заказы остаются для учёта, но без контактов.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Неверный пароль']);
        }
        DB::transaction(function () use ($user) {
            Subscription::query()->where('user_id', $user->id)->whereIn('status', ['active', 'past_due', 'grace', 'pending'])
                ->update(['auto_renew' => false, 'status' => 'cancelled', 'cancelled_at' => now()]);
            $user->paymentMethods()->delete();
            DB::table('cart_items')->where('user_id', $user->id)->delete();
            DB::table('favorites')->where('user_id', $user->id)->delete();
            $user->orders()->update(['contact_name' => 'Удалён', 'contact_phone' => '+70000000000', 'contact_email' => null, 'address' => null]);
            DB::table('reviews')->where('user_id', $user->id)->update(['author_name' => 'Покупатель']);
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Удалённый пользователь',
                'email' => 'deleted-'.$user->id.'-'.Str::random(8).'@invalid',
                'phone' => null,
                'password' => Str::random(40),
                'telegram_chat_id' => null,
                'marketing_consent' => false,
                'two_factor_secret' => null,
                'is_active' => false,
                'anonymized_at' => now(),
            ])->save();
        });

        return response()->json(null, 204);
    }
}
