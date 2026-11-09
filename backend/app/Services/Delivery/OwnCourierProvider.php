<?php

namespace App\Services\Delivery;

use App\Models\DeliveryZone;
use App\Models\Order;

/** Собственная курьерская доставка по Москве: зоны-многоугольники с тарифами из админки. */
class OwnCourierProvider implements DeliveryProvider
{
    public function code(): string
    {
        return 'courier';
    }

    public function zoneFor(float $lat, float $lng): ?DeliveryZone
    {
        return DeliveryZone::query()->where('is_active', true)->orderBy('sort')->get()
            ->first(fn (DeliveryZone $z) => $z->contains($lat, $lng));
    }

    public function quote(?array $address, int $subtotal): DeliveryQuote
    {
        if (! isset($address['lat'], $address['lng'])) {
            return DeliveryQuote::unavailable('courier', 'Выберите адрес из подсказок, чтобы рассчитать доставку');
        }
        $zone = $this->zoneFor((float) $address['lat'], (float) $address['lng']);
        if (! $zone) {
            return DeliveryQuote::unavailable('courier', 'Адрес вне зоны курьерской доставки. Выберите самовывоз, СДЭК или заказ через Flowwow.');
        }
        if ($subtotal < $zone->min_order) {
            return new DeliveryQuote('courier', false, 0, '', $zone->id,
                'Минимальная сумма заказа для зоны «'.$zone->name.'» — '.number_format($zone->min_order / 100, 0, ',', ' ').' ₽');
        }

        return new DeliveryQuote('courier', true, $zone->costFor($subtotal), $zone->etaText(), $zone->id);
    }

    public function createShipment(Order $order): ?string
    {
        return null; // доставка создаётся DeliveryService::createFor()
    }

    public function track(Order $order): array
    {
        return $order->delivery?->history->map(fn ($h) => ['status' => $h->to, 'at' => $h->created_at->toIso8601String(), 'comment' => $h->comment])->all() ?? [];
    }
}
