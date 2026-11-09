<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Кешбэк-баллы (1 балл = 1 ₽). Журнал неизменяемый, баланс = сумма записей.
 * Начисление — после выдачи заказа (Completed) или при покупке в точке (скан QR продавцом).
 * Списание ограничено долей заказа (LOYALTY_MAX_SPEND_SHARE). Сгорание — командой loyalty:expire.
 * Правила (процент, доля, срок жизни) — [УТОЧНИТЬ], значения в config/shop.php.
 */
class LoyaltyService
{
    public function balance(User $user): int
    {
        return (int) LoyaltyTransaction::query()->where('user_id', $user->id)->sum('amount');
    }

    /** Сколько баллов можно списать с заказа на сумму $amount (копейки) */
    public function maxSpendable(User $user, int $amountKopecks): int
    {
        $byShare = (int) floor($amountKopecks / 100 * config('shop.loyalty.max_spend_share'));

        return max(0, min($this->balance($user), $byShare));
    }

    public function earnPoints(int $amountKopecks): int
    {
        return (int) floor($amountKopecks / 100 * config('shop.loyalty.earn_percent') / 100);
    }

    public function awardForOrder(Order $order): ?LoyaltyTransaction
    {
        if (! $order->user_id || $order->loyalty_awarded_at) {
            return null;
        }
        // Баллы начисляются с суммы, оплаченной деньгами (без доставки и без списанных баллов)
        $base = max(0, $order->total - $order->delivery_cost);
        $points = $this->earnPoints($base);

        return DB::transaction(function () use ($order, $points) {
            $order->forceFill(['loyalty_awarded_at' => now()])->save();
            if ($points <= 0) {
                return null;
            }

            return LoyaltyTransaction::create([
                'user_id' => $order->user_id, 'type' => 'earn', 'amount' => $points, 'order_id' => $order->id,
                'comment' => "Заказ № {$order->number}", 'idempotency_key' => "earn:order:{$order->id}",
                'expires_at' => now()->addDays(config('shop.loyalty.expire_days')),
            ]);
        });
    }

    public function spendForOrder(User $user, Order $order, int $points): void
    {
        if ($points <= 0) {
            return;
        }
        DB::transaction(function () use ($user, $order, $points) {
            // Блокировка строки пользователя сериализует параллельные списания
            User::query()->lockForUpdate()->find($user->id);
            if ($points > $this->balance($user)) {
                throw ValidationException::withMessages(['points' => 'Недостаточно баллов']);
            }
            LoyaltyTransaction::create([
                'user_id' => $user->id, 'type' => 'spend', 'amount' => -$points, 'order_id' => $order->id,
                'comment' => "Оплата заказа № {$order->number}", 'idempotency_key' => "spend:order:{$order->id}",
            ]);
        });
    }

    /** Возврат списанных баллов при отмене заказа */
    public function refundForOrder(Order $order): void
    {
        if (! $order->user_id || $order->points_spent <= 0) {
            return;
        }
        LoyaltyTransaction::query()->firstOrCreate(['idempotency_key' => "refund:order:{$order->id}"], [
            // points_spent в заказе хранится в копейках, баллы — в рублях
            'user_id' => $order->user_id, 'type' => 'refund', 'amount' => intdiv($order->points_spent, 100), 'order_id' => $order->id,
            'comment' => "Отмена заказа № {$order->number}",
        ]);
    }

    /** Операция в точке продаж по QR: начисление за покупку и/или списание. */
    public function pointOfSale(User $customer, User $seller, int $purchaseKopecks, int $spendPoints, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($customer, $seller, $purchaseKopecks, $spendPoints, $idempotencyKey) {
            User::query()->lockForUpdate()->find($customer->id);
            if (LoyaltyTransaction::query()->where('idempotency_key', 'like', "pos:{$idempotencyKey}:%")->exists()) {
                return ['balance' => $this->balance($customer), 'earned' => 0, 'spent' => 0, 'duplicate' => true];
            }
            $max = $this->maxSpendable($customer, $purchaseKopecks);
            if ($spendPoints > $max) {
                throw ValidationException::withMessages(['spend' => "Можно списать не больше {$max} баллов"]);
            }
            if ($spendPoints > 0) {
                LoyaltyTransaction::create(['user_id' => $customer->id, 'type' => 'spend', 'amount' => -$spendPoints,
                    'created_by' => $seller->id, 'comment' => 'Покупка в точке', 'idempotency_key' => "pos:{$idempotencyKey}:spend"]);
            }
            $earned = $this->earnPoints($purchaseKopecks - $spendPoints * 100);
            if ($earned > 0) {
                LoyaltyTransaction::create(['user_id' => $customer->id, 'type' => 'earn', 'amount' => $earned,
                    'created_by' => $seller->id, 'comment' => 'Покупка в точке', 'idempotency_key' => "pos:{$idempotencyKey}:earn",
                    'expires_at' => now()->addDays(config('shop.loyalty.expire_days'))]);
            }

            return ['balance' => $this->balance($customer), 'earned' => $earned, 'spent' => $spendPoints, 'duplicate' => false];
        });
    }

    /**
     * Сгорание: по каждой просроченной записи начисления списываем её неиспользованный остаток (FIFO).
     * Возвращает количество сгоревших баллов.
     */
    public function expire(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $tx = LoyaltyTransaction::query()->where('user_id', $user->id)->orderBy('id')->get();
            $credits = [];
            $debits = 0;
            foreach ($tx as $t) {
                if ($t->amount > 0) {
                    $credits[] = ['id' => $t->id, 'left' => $t->amount, 'expires' => $t->expires_at];
                } else {
                    $debits += -$t->amount;
                }
            }
            // Расход (списания и уже сгоревшее) гасит начисления от старых к новым
            foreach ($credits as &$c) {
                $use = min($c['left'], $debits);
                $c['left'] -= $use;
                $debits -= $use;
            }
            unset($c);
            $expired = 0;
            foreach ($credits as $c) {
                if ($c['left'] > 0 && $c['expires'] && $c['expires']->isPast()) {
                    $expired += $c['left'];
                    LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'expire', 'amount' => -$c['left'],
                        'comment' => 'Сгорание баллов', 'idempotency_key' => "expire:{$c['id']}"]);
                }
            }

            return $expired;
        });
    }
}
