<?php

namespace App\Events;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryStatusChanged
{
    use Dispatchable;

    public function __construct(public Delivery $delivery, public DeliveryStatus $status) {}
}
