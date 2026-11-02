<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Готовый HTML публичных страниц лежит в файловом кэше: следующий запрос не ходит в базу и не рендерит Blade.
 * Кэш сбрасывается при любой правке товаров (см. Product::booted → PageCache::flush()).
 */
class PageCache
{
    private const VERSION_KEY = 'pages:version';

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $ttl = (int) config('shop.page_cache_ttl');
        if (! $request->isMethod('GET') || $ttl <= 0 || app()->environment('local') && $request->has('nocache')) {
            return $next($request);
        }

        $key = 'page:' . Cache::get(self::VERSION_KEY, 1) . ':' . sha1($request->getSchemeAndHttpHost() . $request->getRequestUri());
        $hit = Cache::get($key);
        if ($hit) {
            $response = response($hit['body'], 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } else {
            $response = $next($request);
            if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                Cache::put($key, ['body' => $response->getContent()], $ttl);
            }
        }

        $etag = '"' . md5($response->getContent()) . '"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=30, stale-while-revalidate=600');
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age=30, stale-while-revalidate=600']);
        }

        return $response;
    }
}
