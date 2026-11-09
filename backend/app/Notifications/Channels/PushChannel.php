<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\WebPush;
use Illuminate\Notifications\Notification;

/**
 * Web Push на все устройства пользователя нужной области (customer — основной домен, staff — штат).
 * Уведомление описывает сообщение методом toPush(): ['title', 'body', 'url', 'tag', 'scope'].
 */
class PushChannel
{
    public function __construct(private WebPush $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush') || ! $this->push->enabled()) {
            return;
        }
        $message = $notification->toPush($notifiable);
        $scope = $message['scope'] ?? 'customer';
        unset($message['scope']);
        PushSubscription::query()->where('user_id', $notifiable->id)->where('scope', $scope)->get()
            ->each(fn (PushSubscription $s) => $this->push->send($s, $message));
    }
}
