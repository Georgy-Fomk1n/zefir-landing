<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Выдача по часовым слотам. Правила: график работы и запас времени из config/shop.php. */
class Schedule
{
    public static function parts(): array
    {
        return config('shop.schedule.parts');
    }

    public static function hours(): array
    {
        return array_merge(...array_column(self::parts(), 1));
    }

    public static function isOpenDay(CarbonImmutable $d): bool
    {
        return ! in_array($d->dayOfWeek, config('shop.hours.closed_days'), true);
    }

    public static function openNow(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $t = $now->hour + $now->minute / 60;

        return self::isOpenDay($now) && $t >= config('shop.hours.open') && $t < config('shop.hours.close');
    }

    /** Можно ли выбрать этот час в этот день (дата Y-m-d) */
    public static function valid(string $date, int $hour): bool
    {
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return false;
        }
        if (! $d || $d->format('Y-m-d') !== $date) {
            return false;
        }
        $now = CarbonImmutable::now();
        $diff = (int) $now->startOfDay()->diffInDays($d, false);
        if ($diff < 0 || $diff >= config('shop.schedule.days') || ! self::isOpenDay($d)) {
            return false;
        }
        if (! in_array($hour, self::hours(), true)) {
            return false;
        }

        return ! ($diff === 0 && $hour < $now->hour + config('shop.schedule.lead_hours'));
    }

    /** Для интерфейса: на каждый день список доступных часов */
    public static function data(): array
    {
        $today = CarbonImmutable::today();
        $days = [];
        for ($i = 0; $i < config('shop.schedule.days'); $i++) {
            $d = $today->addDays($i);
            $ds = $d->format('Y-m-d');
            $days[] = [
                'date' => $ds, 'i' => $i, 'dow' => $d->dayOfWeek, 'd' => $d->day, 'm' => $d->month - 1, 'y' => $d->year,
                'closed' => ! self::isOpenDay($d),
                'ok' => array_values(array_filter(self::hours(), fn ($h) => self::valid($ds, $h))),
            ];
        }

        return ['days' => $days, 'parts' => self::parts()];
    }
}
