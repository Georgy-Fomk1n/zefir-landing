<?php

namespace App\Services\Delivery;

use App\Models\Order;

class PickupProvider implements DeliveryProvider
{
    public function code(): string
    {
        return 'pickup';
    }

    public function quote(?array $address, int $subtotal): DeliveryQuote
    {
        return new DeliveryQuote('pickup', true, 0, 'Самовывоз: '.config('shop.delivery.pickup_address'));
    }

    public function createShipment(Order $order): ?string
    {
        return null;
    }

    public function track(Order $order): array
    {
        return [];
    }
}
