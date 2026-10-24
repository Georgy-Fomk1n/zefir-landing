<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use ConfiguredChannels, Queueable;

    public function __construct(public Order $order, public string $headline) {}

    public function via(object $notifiable): array
    {
        return $this->configuredChannels();
    }

    public function toText(object $notifiable): string
    {
        return "Зефир и пряник: заказ № {$this->order->number} — {$this->headline}. Статус: {$this->order->trackingUrl()}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Заказ № {$this->order->number}: {$this->headline}")
            ->greeting('Здравствуйте!')
            ->line("Заказ № {$this->order->number}: {$this->headline}.")
            ->action('Отследить заказ', $this->order->trackingUrl())
            ->salutation('Зефир и пряник, +7 (968) 479-15-20');
    }

    public function toPush(object $notifiable): array
    {
        return ['title' => 'Заказ № '.$this->order->number, 'body' => mb_strtoupper(mb_substr($this->headline, 0, 1)).mb_substr($this->headline, 1), 'url' => rtrim(config('shop.public_url'), '/').'/account/orders/'.$this->order->id, 'tag' => 'order-'.$this->order->id, 'scope' => 'customer'];
    }
}
