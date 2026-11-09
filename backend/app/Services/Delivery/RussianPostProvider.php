<?php

namespace App\Services\Delivery;

use App\Models\Order;

/** Почта России. Mock-режим по умолчанию; реальная интеграция (otpravka.pochta.ru) — [УТОЧНИТЬ]. */
class RussianPostProvider implements DeliveryProvider
{
    public function code(): string
    {
        return 'russian_post';
    }

    public function quote(?array $address, int $subtotal): DeliveryQuote
    {
        if (! config('shop.delivery.russian_post.enabled')) {
            return empty($address['text'])
                ? DeliveryQuote::unavailable('russian_post', 'Укажите адрес с индексом')
                : new DeliveryQuote('russian_post', true, 35000, '5–10 дней (тестовый тариф)');
        }

        return DeliveryQuote::unavailable('russian_post', 'Расчёт Почты России временно недоступен');
    }

    public function createShipment(Order $order): ?string
    {
        return config('shop.delivery.russian_post.enabled') ? null : 'RP-MOCK-'.$order->id;
    }

    public function track(Order $order): array
    {
        return [];
    }
}
