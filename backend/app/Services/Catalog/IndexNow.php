<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow (Яндекс, Bing). Пингуется из CI после деплоя production — только когда страница уже
 * пересобрана и выложена; ключ лежит в корне сайта ({key}.txt). Метод вызывается командой
 * `php artisan seo:indexnow` и деплой-воркфлоу (scripts/indexnow.mjs).
 */
class IndexNow
{
    /** @param list<string> $paths */
    public function submit(array $paths): bool
    {
        $key = config('shop.indexnow.key');
        if (! config('shop.indexnow.enabled') || ! $key || $paths === []) {
            return false;
        }
        $base = rtrim(config('shop.public_url'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        $res = Http::timeout(10)->post(config('shop.indexnow.endpoint'), [
            'host' => $host,
            'key' => $key,
            'keyLocation' => "{$base}/{$key}.txt",
            'urlList' => array_map(fn ($p) => $base.$p, array_values(array_unique($paths))),
        ]);
        if (! in_array($res->status(), [200, 202], true)) {
            Log::warning('IndexNow rejected', ['status' => $res->status()]);

            return false;
        }

        return true;
    }
}
