<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $url) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter(array_map(fn ($c) => match (trim($c)) {
            'mail' => 'mail', 'telegram' => TelegramChannel::class, 'sms' => SmsChannel::class, default => null,
        }, config('shop.notifications.channels'))));
    }

    public function toText(object $notifiable): string
    {
        return "Абонемент «{$this->subscription->plan->name}» заканчивается {$this->subscription->current_period_end?->format('d.m.Y')}. Продлить: {$this->url}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Продлите абонемент «'.$this->subscription->plan->name.'»')
            ->line('Абонемент заканчивается '.$this->subscription->current_period_end?->format('d.m.Y').'.')
            ->action('Оплатить продление', $this->url);
    }
}
