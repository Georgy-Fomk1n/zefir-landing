<?php

namespace App\Services\Catalog;

use App\Jobs\DispatchStaticRebuild;
use Illuminate\Support\Facades\Cache;

/**
 * Пересборка пререндеренного сайта после изменения каталога: repository_dispatch в GitHub Actions.
 *  • обычные правки — дебаунс 5 минут (серия правок в админке = одна сборка);
 *  • смена цены — сразу, чтобы цена в статике и в API не расходилась дольше одной сборки (~3–5 мин).
 * Расхождение между сборками закрывается живым запросом цены/остатка после гидратации (useLiveProduct),
 * а при оформлении сервер всегда пересчитывает сумму по актуальным ценам.
 */
class StaticRebuild
{
    private const PENDING_KEY = 'static-rebuild:pending-paths';

    private const SCHEDULED_KEY = 'static-rebuild:scheduled';

    /** @param list<string> $paths */
    public function request(array $paths, bool $immediate = false): void
    {
        if (! config('shop.rebuild.enabled')) {
            return;
        }
        $pending = array_values(array_unique([...Cache::get(self::PENDING_KEY, []), ...$paths]));
        Cache::put(self::PENDING_KEY, $pending, now()->addHour());

        if ($immediate) {
            Cache::forget(self::SCHEDULED_KEY);
            DispatchStaticRebuild::dispatch();

            return;
        }
        // Дебаунс: ставим одну отложенную задачу, пока она не выполнилась
        if (Cache::add(self::SCHEDULED_KEY, true, now()->addMinutes(config('shop.rebuild.debounce_minutes') + 1))) {
            DispatchStaticRebuild::dispatch()->delay(now()->addMinutes(config('shop.rebuild.debounce_minutes')));
        }
    }

    /** @return list<string> */
    public function pullPending(): array
    {
        Cache::forget(self::SCHEDULED_KEY);

        return Cache::pull(self::PENDING_KEY, []);
    }
}
