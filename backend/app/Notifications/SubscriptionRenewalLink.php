<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalLink extends Notification implements ShouldQueue
{
    use ConfiguredChannels, Queueable;

    public function __construct(public Subscription $subscription, public string $url) {}

    public function via(object $notifiable): array
    {
        return $this->configuredChannels();
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

    public function toPush(object $notifiable): array
    {
        return ['title' => 'Абонемент «'.$this->subscription->plan->name.'»', 'body' => 'Заканчивается '.$this->subscription->current_period_end?->format('d.m.Y').'. Нажмите, чтобы продлить.', 'url' => $this->url, 'tag' => 'subscription-'.$this->subscription->id, 'scope' => 'customer'];
    }
}
