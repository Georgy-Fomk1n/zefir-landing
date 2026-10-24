<?php

namespace App\Services\Geo;

interface GeoSuggest
{
    /** @return list<array{text: string, lat: float, lng: float}> */
    public function suggest(string $query): array;
}
