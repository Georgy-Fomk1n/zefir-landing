<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Яндекс: Геосаджест (suggest-maps.yandex.ru/v1/suggest) с print_address и attrs=uri,
 * координаты — через HTTP-геокодер по uri. Требует ключи обоих API [УТОЧНИТЬ тариф].
 */
class YandexGeoSuggest implements GeoSuggest
{
    public function suggest(string $query): array
    {
        $key = config('shop.geo.yandex_key');

        return Cache::remember('geo:yandex:'.md5($query), 3600, function () use ($query, $key) {
            $res = Http::timeout(5)->get('https://suggest-maps.yandex.ru/v1/suggest', [
                'apikey' => $key, 'text' => 'Москва, '.$query, 'lang' => 'ru_RU', 'types' => 'house', 'attrs' => 'uri', 'results' => 7,
            ]);
            if (! $res->successful()) {
                return [];
            }
            $out = [];
            foreach ($res->json('results', []) as $r) {
                $uri = $r['uri'] ?? null;
                if (! $uri) {
                    continue;
                }
                $geo = Http::timeout(5)->get('https://geocode-maps.yandex.ru/1.x/', ['apikey' => $key, 'uri' => $uri, 'format' => 'json']);
                $pos = $geo->json('response.GeoObjectCollection.featureMember.0.GeoObject.Point.pos');
                if ($pos) {
                    [$lng, $lat] = array_map('floatval', explode(' ', $pos));
                    $out[] = ['text' => trim(($r['title']['text'] ?? '').', '.($r['subtitle']['text'] ?? ''), ', '), 'lat' => $lat, 'lng' => $lng];
                }
            }

            return $out;
        });
    }
}
