<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/** SMS. Провайдер [УТОЧНИТЬ]; по умолчанию сообщения пишутся в лог (SMS_PROVIDER=log). */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;
        $phone ??= $notifiable->phone ?? null;
        if (! $phone || ! method_exists($notification, 'toText')) {
            return;
        }
        Log::info('sms', ['to' => $phone, 'text' => $notification->toText($notifiable)]);
    }
}
