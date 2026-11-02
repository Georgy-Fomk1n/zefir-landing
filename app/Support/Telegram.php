<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Telegram
{
    public static function send(string $text): void
    {
        $token = config('shop.notify.telegram_token');
        $chat = config('shop.notify.telegram_chat_id');
        if (! $token || ! $chat) {
            return;
        }
        try {
            $r = Http::timeout(6)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chat, 'text' => $text]);
            if (! $r->successful()) {
                Log::warning('Telegram: ' . $r->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram: ' . $e->getMessage());
        }
    }
}
