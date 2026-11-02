<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Заказ')->columns(3)->schema([
                TextEntry::make('id')->label('Номер')->formatStateUsing(fn ($state) => '№ ' . (1000 + $state)),
                TextEntry::make('status')->label('Статус')->badge()->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'new' => 'warning', 'confirmed' => 'info', 'ready' => 'success', 'cancelled' => 'danger', default => 'gray' }),
                TextEntry::make('created_at')->label('Оформлен')->dateTime('j F, H:i'),
                TextEntry::make('name')->label('Покупатель'),
                TextEntry::make('phone')->label('Телефон')->url(fn (Order $o) => 'tel:' . preg_replace('/[^\d+]/', '', $o->phone))->copyable(),
                TextEntry::make('slot')->label('Когда')->state(fn (Order $o) => $o->slotLabel()),
                TextEntry::make('shipping')->label('Получение')->formatStateUsing(fn ($state) => $state === 'courier' ? 'Курьером' : 'Самовывоз'),
                TextEntry::make('address')->label('Адрес')->columnSpan(2)->copyable(),
                TextEntry::make('comment')->label('Комментарий')->placeholder('—')->columnSpanFull(),
                TextEntry::make('card_text')->label('Текст открытки')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Состав')->schema([
                RepeatableEntry::make('items')->label('Позиции заказа')->columns(4)->schema([
                    TextEntry::make('name')->label('Товар')->columnSpan(2),
                    TextEntry::make('qty')->label('Кол-во'),
                    TextEntry::make('sum')->label('Сумма')->formatStateUsing(fn ($state) => rub((int) $state)),
                ]),
                TextEntry::make('fee')->label('Доставка')->formatStateUsing(fn ($state) => rub((int) $state)),
                TextEntry::make('total')->label('Итого')->weight('bold')->size('lg')->formatStateUsing(fn ($state) => rub((int) $state)),
            ]),
        ]);
    }
}
