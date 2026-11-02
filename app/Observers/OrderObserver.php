<?php

namespace App\Observers;

use App\Models\Order;
use App\Support\Push;

class OrderObserver
{
    /** Статус заказа изменился в админке: сообщаем покупателю push-уведомлением */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }
        if ($msg = $order->statusPush()) {
            Push::toOrder($order, $msg[0], $msg[1]);
        }
    }
}
