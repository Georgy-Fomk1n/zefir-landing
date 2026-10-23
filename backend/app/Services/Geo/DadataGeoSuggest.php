<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** DaData: suggestions/api/4_1/rs/suggest/address, ограничение Москвой. Ключ хранится только на сервере. */
class DadataGeoSuggest implements GeoSuggest
{
    public function suggest(string $query): array
    {
        return Cache::remember('geo:dadata:'.md5($query), 3600, function () use ($query) {
            $res = Http::withHeaders(['Authorization' => 'Token '.config('shop.geo.dadata_token')])
                ->timeout(5)
                ->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', [
                    'query' => $query,
                    'count' => 7,
                    'locations' => [['city' => 'Москва']],
                ]);
            if (! $res->successful()) {
                return [];
            }

            return collect($res->json('suggestions', []))
                ->filter(fn ($s) => ! empty($s['data']['geo_lat']) && ! empty($s['data']['geo_lon']))
                ->map(fn ($s) => ['text' => $s['value'], 'lat' => (float) $s['data']['geo_lat'], 'lng' => (float) $s['data']['geo_lon']])
                ->values()->all();
        });
    }
}
