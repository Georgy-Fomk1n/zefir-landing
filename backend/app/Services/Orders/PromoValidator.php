<?php

namespace App\Services\Orders;

use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;

/**
 * Проверка промокода: активность, срок, общий лимит, лимит на покупателя (по user_id или телефону),
 * минимальная сумма, ограничение по категориям (скидка считается только с товаров этих категорий).
 */
class PromoValidator
{
    /**
     * @param  list<array{category_id: int, sum: int}>  $lines
     * @return array{promo: ?PromoCode, discount: int, error: ?string}
     */
    public function apply(?string $code, array $lines, ?int $userId, ?string $phone): array
    {
        $code = $code !== null ? mb_strtoupper(trim($code)) : '';
        if ($code === '') {
            return ['promo' => null, 'discount' => 0, 'error' => null];
        }
        $promo = PromoCode::query()->where('code', $code)->first();
        $error = match (true) {
            ! $promo || ! $promo->is_active => 'Промокод не найден',
            $promo->starts_at && $promo->starts_at->isFuture() => 'Промокод ещё не действует',
            $promo->ends_at && $promo->ends_at->isPast() => 'Срок действия промокода истёк',
            $promo->max_uses !== null && $promo->used_count >= $promo->max_uses => 'Промокод больше недоступен',
            default => null,
        };
        if ($error) {
            return ['promo' => null, 'discount' => 0, 'error' => $error];
        }

        if ($promo->per_user_limit !== null && ($userId || $phone)) {
            $used = DB::table('promo_redemptions')->where('promo_code_id', $promo->id)
                ->where(fn ($q) => $q->when($userId, fn ($w) => $w->orWhere('user_id', $userId))->when($phone, fn ($w) => $w->orWhere('phone', self::normalizePhone($phone))))
                ->count();
            if ($used >= $promo->per_user_limit) {
                return ['promo' => null, 'discount' => 0, 'error' => 'Вы уже использовали этот промокод'];
            }
        }

        $total = array_sum(array_column($lines, 'sum'));
        if ($total < $promo->min_total) {
            return ['promo' => null, 'discount' => 0, 'error' => 'Минимальная сумма для промокода — '.number_format($promo->min_total / 100, 0, ',', ' ').' ₽'];
        }
        $eligible = $promo->category_ids
            ? array_sum(array_column(array_filter($lines, fn ($l) => in_array($l['category_id'], $promo->category_ids, true)), 'sum'))
            : $total;
        if ($eligible <= 0) {
            return ['promo' => null, 'discount' => 0, 'error' => 'Промокод не действует на товары в корзине'];
        }
        $discount = $promo->type === 'percent'
            ? (int) floor($eligible * min(100, $promo->value) / 100)
            : min($eligible, $promo->value);

        return ['promo' => $promo, 'discount' => $discount, 'error' => null];
    }

    public static function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7'.substr($d, 1);
        }

        return '+'.$d;
    }
}
