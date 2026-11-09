<?php

namespace App\Http\Controllers;

use App\Http\Presenters\OrderPresenter;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Order;
use App\Services\Geo\GeoSuggest;
use App\Services\Orders\OrderPlacement;
use App\Services\Orders\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function slots(Request $request, SlotService $slots): JsonResponse
    {
        $type = $request->validate(['type' => ['required', 'in:pickup,courier']])['type'];

        return response()->json(['data' => $slots->available($type)->map(fn ($s) => $s->toWindow() + ['id' => $s->id, 'available' => $s->available()])->values()]);
    }

    public function suggest(Request $request, GeoSuggest $geo): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:3', 'max:200']])['q'];

        return response()->json(['data' => $geo->suggest($q)]);
    }

    public function place(PlaceOrderRequest $request, OrderPlacement $placement): JsonResponse
    {
        $user = $request->user('sanctum');
        $user = $user?->tokenCan('customer') ? $user : null;
        $result = $placement->place($request->validated(), $user);
        if ($user) {
            DB::table('cart_items')->where('user_id', $user->id)->delete();
        }

        return response()->json([
            'data' => OrderPresenter::make($result['order']->refresh(), $user),
            'payment' => ['url' => $result['url']],
        ], 201);
    }

    /** Отслеживание без входа по токену из уведомления. Без ПДн и сумм. */
    public function track(string $token): JsonResponse
    {
        $order = Order::query()->where('tracking_token', $token)->where('created_at', '>', now()->subDays(60))->firstOrFail();

        return response()->json(['data' => OrderPresenter::make($order, null, public: true)]);
    }

    /** Повторная попытка оплаты по ссылке отслеживания (после отказа банка или закрытой страницы оплаты) */
    public function pay(string $token, OrderPlacement $placement): JsonResponse
    {
        $order = Order::query()->where('tracking_token', $token)->firstOrFail();

        return response()->json(['payment' => ['url' => $placement->startPayment($order)['url']]]);
    }
}
