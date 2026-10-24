<?php

namespace App\Services\Orders;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Оформление: в одной транзакции пересчёт по актуальным ценам, резерв остатков (с таймаутом),
 * бронь слота, списание баллов, фиксация промокода; затем — создание платежа.
 */
class OrderPlacement
{
    public function __construct(
        private CartPricing $pricing,
        private SlotService $slots,
        private LoyaltyService $loyalty,
        private PaymentService $payments,
        private OrderLifecycle $lifecycle,
    ) {}

    /**
     * @param  array{lines: list<array{variantId: int, qty: int}>, name: string, phone: string, email?: ?string, fulfilment: string, slotId?: ?int, address?: ?array, comment?: ?string, promoCode?: ?string, points?: ?int}  $data
     * @return array{order: Order, payment: ?Payment, url: string}
     */
    public function place(array $data, ?User $user): array
    {
        $fulfilment = Fulfilment::from($data['fulfilment']);
        if ($fulfilment->needsSlot() && empty($data['slotId'])) {
            throw ValidationException::withMessages(['slotId' => 'Выберите время']);
        }
        $phone = PromoValidator::normalizePhone($data['phone']);

        $order = DB::transaction(function () use ($data, $user, $fulfilment, $phone) {
            // Блокируем варианты, чтобы два покупателя не зарезервировали последний товар
            $ids = collect($data['lines'])->pluck('variantId')->all();
            DB::table('product_variants')->whereIn('id', $ids)->lockForUpdate()->get();

            $q = $this->pricing->quote($data['lines'], $fulfilment, $data['address'] ?? null, $data['promoCode'] ?? null,
                (int) ($data['points'] ?? 0), $user, $phone, strict: true);
            if ($q['lines']->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Корзина пуста']);
            }

            $slot = $fulfilment->needsSlot() ? $this->slots->book((int) $data['slotId'], $fulfilment === Fulfilment::Courier ? 'courier' : 'pickup') : null;

            $order = Order::create([
                'number' => $this->nextNumber(),
                'user_id' => $user?->id,
                'status' => OrderStatus::PendingPayment,
                'fulfilment' => $fulfilment,
                'contact_name' => $data['name'],
                'contact_phone' => $phone,
                'contact_email' => ($data['email'] ?? null) ?: $user?->email,
                'address' => $fulfilment->needsAddress() ? $data['address'] : null,
                'slot_id' => $slot?->id,
                'zone_id' => $q['delivery']?->zoneId,
                'promo_code_id' => $q['promo']?->id,
                'subtotal' => $q['subtotal'],
                'discount' => $q['discount'],
                'points_spent' => $q['pointsSpent'] * 100,
                'delivery_cost' => $q['deliveryCost'],
                'total' => $q['total'],
                'payment_status' => PaymentStatus::Pending,
                'comment' => $data['comment'] ?? null,
                'reserved_until' => now()->addMinutes(config('shop.orders.reserve_minutes')),
                'tracking_token' => Str::random(40),
            ]);
            foreach ($q['lines'] as $l) {
                $v = $l['variant'];
                $order->items()->create([
                    'product_variant_id' => $v->id, 'product_id' => $v->product_id, 'category_id' => $v->product->category_id,
                    'product_name' => $v->product->name, 'variant_name' => $v->name, 'sku' => $v->sku, 'price' => $v->price, 'qty' => $l['qty'],
                ]);
                $v->increment('reserved', $l['qty']);
            }
            $order->history()->create(['from' => null, 'to' => OrderStatus::PendingPayment->value, 'user_id' => $user?->id]);
            if ($q['promo']) {
                $q['promo']->increment('used_count');
                DB::table('promo_redemptions')->insert(['promo_code_id' => $q['promo']->id, 'order_id' => $order->id, 'user_id' => $user?->id, 'phone' => $phone, 'created_at' => now()]);
            }
            if ($user && $q['pointsSpent'] > 0) {
                $this->loyalty->spendForOrder($user, $order, $q['pointsSpent']);
            }

            return $order;
        });

        return $this->startPayment($order);
    }

    /**
     * Ссылка на оплату заказа. Используется при оформлении и для повторной попытки после отказа банка
     * (пока заказ ждёт оплаты и резерв не истёк).
     *
     * @return array{order: Order, payment: ?Payment, url: string}
     */
    public function startPayment(Order $order): array
    {
        if ($order->status !== OrderStatus::PendingPayment || ($order->reserved_until && $order->reserved_until->isPast())) {
            throw ValidationException::withMessages(['order' => 'Заказ уже нельзя оплатить']);
        }
        $base = rtrim(config('shop.public_url'), '/');
        $back = $order->user_id ? "{$base}/account/orders/{$order->id}" : "{$base}/track/?t={$order->tracking_token}";
        $success = $back.(str_contains($back, '?') ? '&' : '?').'paid=1';
        if ($order->total <= 0) {
            // Полностью покрыт промокодом: оплата не нужна
            $this->lifecycle->markPaid($order);

            return ['order' => $order->refresh(), 'payment' => null, 'url' => $success];
        }
        $payment = $this->payments->start(
            $order, $order->total, "Заказ № {$order->number} — Зефир и пряник", $success, $back,
            false, $order->items->map(fn ($i) => ['name' => $i->product_name.' ('.$i->variant_name.')', 'price' => $i->price, 'qty' => $i->qty])->all(),
        );

        return ['order' => $order, 'payment' => $payment, 'url' => (string) $payment->payment_url];
    }

    private function nextNumber(): string
    {
        $prefix = config('shop.orders.number_prefix').now(config('shop.timezone'))->format('ymd');
        for ($i = 0; $i < 5; $i++) {
            $number = $prefix.'-'.strtoupper(Str::random(4));
            if (! Order::query()->where('number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix.'-'.strtoupper(Str::random(8));
    }
}
