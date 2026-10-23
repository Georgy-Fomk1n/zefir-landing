<?php

namespace App\Services\Delivery;

use App\Models\Order;

/**
 * Способ получения заказа. Реализации: самовывоз, собственный курьер (зоны Москвы), СДЭК, Почта России.
 * Для разработки СДЭК и Почта работают в mock-режиме (CDEK_ENABLED / RUSSIAN_POST_ENABLED = false).
 */
interface DeliveryProvider
{
    public function code(): string;

    /** @param array{text?: ?string, lat?: ?float, lng?: ?float}|null $address */
    public function quote(?array $address, int $subtotal): DeliveryQuote;

    /** Создать отправление; вернуть внешний трек-номер (или null для собственных способов). */
    public function createShipment(Order $order): ?string;

    /** @return list<array{status: string, at: string, comment: ?string}> */
    public function track(Order $order): array;
}
