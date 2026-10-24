<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Изменение каталога в админке: пересборка статики и IndexNow. */
class CatalogChanged
{
    use Dispatchable;

    /** @param list<string> $paths изменённые публичные пути, например /catalog/vkusy/kofe/ */
    public function __construct(public array $paths, public bool $priceChanged = false, public bool $deleted = false) {}
}
