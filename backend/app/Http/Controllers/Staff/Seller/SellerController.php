<?php

namespace App\Http\Controllers\Staff\Seller;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\QrToken;
use App\Services\Orders\OrderLifecycle;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Панель продавца: очередь заказов ко времени, статусы, сканирование QR лояльности и абонементов. */
class SellerController extends Controller
{
    /** Очередь на дату, сгруппированная по слотам */
    public function queue(Request $request): JsonResponse
    {
        $date = $request->query('date', now(config('shop.timezone'))->toDateString());
        $orders = Order::query()->with(['items', 'history', 'slot', 'delivery.history', 'delivery.courier', 'zone'])
            ->whereNotIn('status', [OrderStatus::PendingPayment->value])
            ->where(fn ($q) => $q->whereHas('slot', fn ($s) => $s->whereDate('date', $date))
                ->orWhere(fn ($w) => $w->whereNull('slot_id')->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])))
            ->get()
            ->sortBy(fn (Order $o) => ($o->slot ? $o->slot->date->format('Y-m-d').' '.$o->slot->starts_at : '9999').'-'.$o->id)
            ->values();

        return response()->json(['data' => $orders->map(OrderPresenter::staff(...))]);
    }

    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, OrderStatus::sellerManaged()))],
            'comment' => ['nullable', 'string', 'max:300'],
        ]);
        $to = OrderStatus::from($data['status']);
        if ($to === OrderStatus::HandedToCourier) {
            throw ValidationException::withMessages(['status' => 'Передача курьеру — через назначение курьера']);
        }
        if ($to === OrderStatus::Shipped && ! in_array($order->fulfilment, [Fulfilment::Cdek, Fulfilment::RussianPost], true)) {
            throw ValidationException::withMessages(['status' => 'Отправка доступна только для СДЭК и Почты']);
        }
        if ($to === OrderStatus::Completed && $order->fulfilment !== Fulfilment::Pickup && $order->status === OrderStatus::Ready) {
            throw ValidationException::withMessages(['status' => 'Доставочный заказ закрывает курьер или служба доставки']);
        }
        $order->transitionTo($to, $request->user(), $data['comment'] ?? null);

        return response()->json(['data' => OrderPresenter::staff($order->refresh())]);
    }

    public function cancel(Request $request, Order $order, OrderLifecycle $lifecycle): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'];
        $lifecycle->cancel($order, $request->user(), $reason);

        return response()->json(['data' => OrderPresenter::staff($order->refresh())]);
    }

    /** Сканирование QR карты лояльности: показать баланс; с суммой покупки — начислить/списать. */
    public function loyaltyScan(Request $request, QrToken $qr, LoyaltyService $loyalty): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'purchase' => ['nullable', 'integer', 'min:0'],
            'spend' => ['nullable', 'integer', 'min:0'],
            'operationId' => ['required_with:purchase', 'nullable', 'string', 'max:64'],
        ]);
        $customer = User::query()->where('is_active', true)->findOrFail($qr->verify($data['token'], 'loyalty'));
        if (! isset($data['purchase'])) {
            return response()->json(['data' => ['name' => $customer->name, 'balance' => $loyalty->balance($customer)]]);
        }
        $result = $loyalty->pointOfSale($customer, $request->user(), (int) $data['purchase'], (int) ($data['spend'] ?? 0), $data['operationId']);

        return response()->json(['data' => ['name' => $customer->name] + $result]);
    }

    public function subscriptionScan(Request $request, QrToken $qr, SubscriptionService $subscriptions): JsonResponse
    {
        $token = $request->validate(['token' => ['required', 'string', 'max:500']])['token'];
        $sub = Subscription::query()->with(['plan', 'user'])->findOrFail($qr->verify($token, 'subscription'));
        $result = $subscriptions->redeem($sub, $request->user());

        return response()->json(['data' => $result + ['customer' => $sub->user->name]]);
    }
}
