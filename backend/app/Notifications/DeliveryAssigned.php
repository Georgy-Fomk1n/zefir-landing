<?php

namespace App\Notifications;

use App\Models\Delivery;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Курьеру: назначена доставка. Без персональных данных клиента в тексте уведомления. */
class DeliveryAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Delivery $delivery) {}

    public function via(object $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush(object $notifiable): array
    {
        $slot = $this->delivery->order->slot?->toWindow();

        return [
            'title' => 'Новая доставка',
            'body' => 'Заказ № '.$this->delivery->order->number.($slot ? " · {$slot['from']}–{$slot['to']}" : ''),
            'url' => rtrim(config('shop.staff_url'), '/').'/courier',
            'tag' => 'delivery-'.$this->delivery->id,
            'scope' => 'staff',
        ];
    }
}
