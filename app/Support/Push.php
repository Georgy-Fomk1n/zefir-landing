<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Web Push по протоколу VAPID. Отправка идёт после ответа пользователю, поэтому очередь и cron не нужны. */
class Push
{
    public static function configured(): bool
    {
        return (bool) (config('shop.vapid.public') && config('shop.vapid.private'));
    }

    public static function toOwners(string $title, string $body, string $url = '/admin'): void
    {
        self::dispatch(fn () => PushSubscription::where('kind', 'owner')->get(), $title, $body, $url);
    }

    public static function toOrder(Order $order, string $title, string $body): void
    {
        self::dispatch(fn () => PushSubscription::where('kind', 'customer')->where('order_id', $order->id)->get(), $title, $body, route('order', $order->token));
    }

    private static function dispatch(\Closure $subs, string $title, string $body, string $url): void
    {
        if (! self::configured()) {
            return;
        }
        // afterResponse: пользователь не ждёт HTTP-запросов к push-сервисам
        dispatch(function () use ($subs, $title, $body, $url) {
            self::send($subs(), $title, $body, $url);
        })->afterResponse();
    }

    /**
     * Библиотека шлёт E_USER_NOTICE, если на хостинге нет ни GMP, ни BCMath (работает и без них, просто медленнее).
     * Laravel превращает notice в исключение, поэтому именно это сообщение гасим.
     */
    public static function client(?array $guzzle = null): WebPush
    {
        $prev = set_error_handler(fn ($no, $str) => $no === E_USER_NOTICE && str_contains($str, 'GMP or BCMath'));
        try {
            return new WebPush(['VAPID' => [
                'subject' => config('shop.vapid.subject'),
                'publicKey' => config('shop.vapid.public'),
                'privateKey' => config('shop.vapid.private'),
            ]], ['TTL' => 3600 * 12], 8, $guzzle ?? []);
        } finally {
            restore_error_handler();
        }
    }

    public static function send(Collection $subs, string $title, string $body, string $url): int
    {
        if ($subs->isEmpty() || ! self::configured()) {
            return 0;
        }
        $webPush = self::client();

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'icon' => '/icons/icon-192.png', 'badge' => '/icons/badge-96.png'], JSON_UNESCAPED_UNICODE);
        $byEndpoint = $subs->keyBy('endpoint');
        foreach ($subs as $s) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint, 'publicKey' => $s->p256dh, 'authToken' => $s->auth, 'contentEncoding' => $s->encoding,
            ]), $payload);
        }

        $ok = 0;
        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $ok++;
                } elseif ($report->isSubscriptionExpired()) {
                    $byEndpoint[$report->getEndpoint()]?->delete();   // подписка протухла: убираем
                } else {
                    Log::warning('Push: ' . $report->getReason());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Push: ' . $e->getMessage());
        }

        return $ok;
    }
}
