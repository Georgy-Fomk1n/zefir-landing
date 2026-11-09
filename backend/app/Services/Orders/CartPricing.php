<?php

namespace App\Services\Orders;

use App\Enums\Fulfilment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Delivery\DeliveryProviders;
use App\Services\Delivery\DeliveryQuote;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Расчёт суммы заказа на сервере по актуальным ценам. Порядок: товары → промокод → баллы → доставка.
 * Баллы ограничены долей суммы после промокода; доставка баллами не оплачивается.
 */
class CartPricing
{
    public function __construct(
        private PromoValidator $promos,
        private LoyaltyService $loyalty,
        private DeliveryProviders $delivery,
    ) {}

    /**
     * @param  list<array{variantId: int, qty: int}>  $lines
     * @return array{lines: Collection, subtotal: int, discount: int, promo: mixed, promoError: ?string, pointsSpent: int, maxPoints: int, delivery: ?DeliveryQuote, deliveryCost: int, total: int}
     */
    public function quote(array $lines, Fulfilment $fulfilment, ?array $address, ?string $promoCode, int $points, ?User $user, ?string $phone = null, bool $strict = false): array
    {
        $qty = collect($lines)->groupBy('variantId')->map(fn ($g) => (int) $g->sum('qty'))->filter(fn ($q) => $q > 0);
        $variants = ProductVariant::query()->with('product.category')->whereIn('id', $qty->keys())->get()->keyBy('id');

        $resolved = $qty->map(function (int $q, $id) use ($variants, $strict) {
            $v = $variants->get($id);
            if (! $v || ! $v->is_active || ! $v->product->is_active) {
                if ($strict) {
                    throw ValidationException::withMessages(['lines' => 'Товар больше не продаётся, обновите корзину']);
                }

                return null;
            }
            if ($strict && $v->available() < $q) {
                throw ValidationException::withMessages(['lines' => "«{$v->product->name}»: в наличии {$v->available()} шт."]);
            }

            return ['variant' => $v, 'qty' => $q, 'sum' => $v->price * $q, 'category_id' => $v->product->category_id];
        })->filter()->values();

        $subtotal = (int) $resolved->sum('sum');
        $promo = $this->promos->apply($promoCode, $resolved->map(fn ($l) => ['category_id' => $l['category_id'], 'sum' => $l['sum']])->all(), $user?->id, $phone);
        if ($strict && $promo['error']) {
            throw ValidationException::withMessages(['promoCode' => $promo['error']]);
        }
        $afterPromo = $subtotal - $promo['discount'];

        $maxPoints = $user ? $this->loyalty->maxSpendable($user, $afterPromo) : 0;
        if ($strict && $points > $maxPoints) {
            throw ValidationException::withMessages(['points' => "Можно списать не больше {$maxPoints} баллов"]);
        }
        $pointsSpent = min(max(0, $points), $maxPoints);

        $delivery = $subtotal > 0 ? $this->delivery->for($fulfilment)->quote($address, $afterPromo) : null;
        if ($strict && $delivery && ! $delivery->available) {
            throw ValidationException::withMessages(['address' => $delivery->message]);
        }
        $deliveryCost = $delivery?->available ? $delivery->cost : 0;

        return [
            'lines' => $resolved,
            'subtotal' => $subtotal,
            'discount' => $promo['discount'],
            'promo' => $promo['promo'],
            'promoError' => $promo['error'],
            'pointsSpent' => $pointsSpent,
            'maxPoints' => $maxPoints,
            'delivery' => $delivery,
            'deliveryCost' => $deliveryCost,
            'total' => $afterPromo - $pointsSpent * 100 + $deliveryCost,
        ];
    }
}
