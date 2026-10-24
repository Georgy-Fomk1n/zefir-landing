<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;

/** Каналы уведомлений покупателю из NOTIFY_CHANNELS (mail, push, telegram, sms). */
trait ConfiguredChannels
{
    protected function configuredChannels(): array
    {
        return array_values(array_filter(array_map(fn ($c) => match (trim($c)) {
            'mail' => 'mail',
            'push' => PushChannel::class,
            'telegram' => TelegramChannel::class,
            'sms' => SmsChannel::class,
            default => null,
        }, config('shop.notifications.channels'))));
    }
}
