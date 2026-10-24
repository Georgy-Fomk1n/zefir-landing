<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payments\PaymentProvider;
use Illuminate\Support\Facades\DB;

/** Последствия смены статусов заказа: остатки, слоты, баллы, промокоды, возвраты. */
class OrderLifecycle
{
    public function __construct(
        private SlotService $slots,
        private LoyaltyService $loyalty,
        private PaymentProvider $payments,
    ) {}

    /** Оплачен: резерв превращается в списание остатков. */
    public function markPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->refresh();
            if ($order->status !== OrderStatus::PendingPayment) {
                // Оплата пришла после отмены по таймауту резерва: деньги возвращаем
                if ($order->status === OrderStatus::Cancelled) {
                    $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();
                    if ($payment) {
                        $this->payments->refund($payment, $payment->amount);
                    }
                }

                return;
            }
            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    DB::table('product_variants')->where('id', $item->product_variant_id)->update([
                        'stock' => DB::raw('CASE WHEN stock >= '.(int) $item->qty.' THEN stock - '.(int) $item->qty.' ELSE 0 END'),
                        'reserved' => DB::raw('CASE WHEN reserved >= '.(int) $item->qty.' THEN reserved - '.(int) $item->qty.' ELSE 0 END'),
                    ]);
                }
            }
            $order->forceFill(['payment_status' => PaymentStatus::Paid, 'reserved_until' => null])->save();
            $order->transitionTo(OrderStatus::Paid, null, 'Оплата получена');
        });
    }

    public function paymentFailed(Order $order): void
    {
        $order->refresh();
        if ($order->status === OrderStatus::PendingPayment) {
            $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
        }
    }

    public function markRefunded(Order $order): void
    {
        $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
    }

    /** Отмена: возвращаем резерв или остатки, слот, баллы и использование промокода; оплаченное — возврат денег. */
    public function cancel(Order $order, ?User $by, string $reason): void
    {
        DB::transaction(function () use ($order, $by, $reason) {
            $order->refresh();
            $wasPaid = $order->payment_status === PaymentStatus::Paid;
            $order->transitionTo(OrderStatus::Cancelled, $by, $reason);
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }
                $wasPaid
                    ? ProductVariant::query()->whereKey($item->product_variant_id)->increment('stock', $item->qty)
                    : DB::table('product_variants')->where('id', $item->product_variant_id)
                        ->update(['reserved' => DB::raw('CASE WHEN reserved >= '.(int) $item->qty.' THEN reserved - '.(int) $item->qty.' ELSE 0 END')]);
            }
            $this->slots->release($order->slot_id);
            $this->loyalty->refundForOrder($order);
            if ($order->promo_code_id) {
                DB::table('promo_codes')->where('id', $order->promo_code_id)->where('used_count', '>', 0)->decrement('used_count');
                DB::table('promo_redemptions')->where('order_id', $order->id)->delete();
            }
            $order->forceFill(['reserved_until' => null])->save();
            if ($wasPaid) {
                /** @var Payment|null $payment */
                $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();
                if ($payment && $this->payments->refund($payment, $payment->amount)) {
                    $payment->update(['status' => PaymentStatus::Refunded]);
                    $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
                }
            }
        });
    }

    /** Снять просроченные резервы неоплаченных заказов (планировщик, каждую минуту). */
    public function releaseExpired(): int
    {
        $n = 0;
        Order::query()->where('status', OrderStatus::PendingPayment)->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())->limit(100)->get()
            ->each(function (Order $o) use (&$n) {
                $this->cancel($o, null, 'Не оплачен вовремя');
                $n++;
            });

        return $n;
    }
}
