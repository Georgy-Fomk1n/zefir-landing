<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = CarbonImmutable::today();
        $active = fn ($q) => $q->whereNotIn('status', ['cancelled']);

        return [
            Stat::make('Новые заказы', Order::where('status', 'new')->count())->description('ждут подтверждения')->color('warning'),
            Stat::make('Выдача сегодня', $active(Order::whereDate('slot_date', $today))->count())->description(rub((int) $active(Order::whereDate('slot_date', $today))->sum('total'))),
            Stat::make('Выдача завтра', $active(Order::whereDate('slot_date', $today->addDay()))->count())->description(rub((int) $active(Order::whereDate('slot_date', $today->addDay()))->sum('total'))),
        ];
    }
}
