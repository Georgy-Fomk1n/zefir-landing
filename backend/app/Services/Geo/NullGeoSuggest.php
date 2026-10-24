<?php

namespace App\Services\Geo;

/** Провайдер не выбран ([УТОЧНИТЬ] Яндекс Геосаджест или DaData): подсказок нет, курьерская доставка недоступна. */
class NullGeoSuggest implements GeoSuggest
{
    public function suggest(string $query): array
    {
        return [];
    }
}
