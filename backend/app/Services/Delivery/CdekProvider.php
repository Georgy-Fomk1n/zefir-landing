<?php

namespace App\Services\Delivery;

use App\Models\Order;

/**
 * СДЭК. В mock-режиме (CDEK_ENABLED=false) отдаёт фиксированный тариф для разработки.
 * Реальная интеграция (API v2: OAuth client_credentials, калькулятор тарифов, создание заказа) — [УТОЧНИТЬ]:
 * нужен договор и тестовые ключи СДЭК; методы подключаются здесь без изменения остального кода.
 */
class CdekProvider implements DeliveryProvider
{
    public function code(): string
    {
        return 'cdek';
    }

    public function quote(?array $address, int $subtotal): DeliveryQuote
    {
        if (! config('shop.delivery.cdek.enabled')) {
            return empty($address['text'])
                ? DeliveryQuote::unavailable('cdek', 'Укажите адрес или пункт выдачи')
                : new DeliveryQuote('cdek', true, 45000, '2–4 дня (тестовый тариф)');
        }

        return DeliveryQuote::unavailable('cdek', 'Расчёт СДЭК временно недоступен');
    }

    public function createShipment(Order $order): ?string
    {
        return config('shop.delivery.cdek.enabled') ? null : 'CDEK-MOCK-'.$order->id;
    }

    public function track(Order $order): array
    {
        return [];
    }
}
