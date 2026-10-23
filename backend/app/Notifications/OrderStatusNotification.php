<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $headline) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter(array_map(fn ($c) => match (trim($c)) {
            'mail' => 'mail',
            'telegram' => TelegramChannel::class,
            'sms' => SmsChannel::class,
            default => null,
        }, config('shop.notifications.channels'))));
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
}
