<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/** Telegram Bot API sendMessage. Получатель — routeNotificationForTelegram() или telegram_chat_id. */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('telegram', $notification) : null;
        $chatId ??= $notifiable->telegram_chat_id ?? null;
        $token = config('shop.notifications.telegram_bot_token');
        if (! $chatId || ! $token || ! method_exists($notification, 'toText')) {
            return;
        }
        Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chatId, 'text' => $notification->toText($notifiable)]);
    }
}
