<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Продавцам и админам: новый оплаченный заказ (push на штат). */
class NewOrderForStaff extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush(object $notifiable): array
    {
        $slot = $this->order->slot?->toWindow();

        return [
            'title' => 'Новый заказ № '.$this->order->number,
            'body' => ($slot ? "К {$slot['from']} {$slot['date']} · " : '').number_format($this->order->total / 100, 0, ',', ' ').' ₽',
            'url' => rtrim(config('shop.staff_url'), '/').'/seller',
            'tag' => 'staff-order-'.$this->order->id,
            'scope' => 'staff',
        ];
    }
}
