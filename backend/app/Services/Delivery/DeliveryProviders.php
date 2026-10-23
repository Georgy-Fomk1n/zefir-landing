<?php

namespace App\Services\Delivery;

use App\Enums\Fulfilment;

class DeliveryProviders
{
    public function __construct(
        private PickupProvider $pickup,
        private OwnCourierProvider $courier,
        private CdekProvider $cdek,
        private RussianPostProvider $post,
    ) {}

    public function for(Fulfilment $f): DeliveryProvider
    {
        return match ($f) {
            Fulfilment::Pickup => $this->pickup,
            Fulfilment::Courier => $this->courier,
            Fulfilment::Cdek => $this->cdek,
            Fulfilment::RussianPost => $this->post,
        };
    }
}
