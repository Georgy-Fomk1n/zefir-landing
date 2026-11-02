<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->formatStateUsing(fn ($state) => 1000 + $state)->sortable(),
                TextColumn::make('created_at')->label('Оформлен')->since()->sortable(),
                TextColumn::make('name')->label('Покупатель')->searchable()->description(fn (Order $o) => $o->phone),
                TextColumn::make('slot_date')->label('Когда')->state(fn (Order $o) => $o->slotLabel())->sortable(query: fn ($q, $dir) => $q->orderBy('slot_date', $dir)->orderBy('slot_hour', $dir)),
                TextColumn::make('shipping')->label('Получение')->badge()->formatStateUsing(fn ($state) => $state === 'courier' ? 'Курьер' : 'Самовывоз')->color(fn ($state) => $state === 'courier' ? 'info' : 'gray'),
                TextColumn::make('total')->label('Сумма')->formatStateUsing(fn ($state) => rub((int) $state))->sortable(),
                // Смена статуса прямо в списке; покупатель получит push (см. OrderObserver)
                SelectColumn::make('status')->label('Статус')->options(Order::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(Order::STATUSES)])
            ->recordActions([ViewAction::make()->label('Открыть')])
            ->recordUrl(fn (Order $o) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $o]))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->emptyStateHeading('Заказов пока нет');
    }
}
