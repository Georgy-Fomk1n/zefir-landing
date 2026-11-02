<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Заказ № ' . $this->record->number();
    }

    protected function getHeaderActions(): array
    {
        $to = fn (string $status, string $label, string $color, string $icon) => Action::make($status)->label($label)->color($color)->icon($icon)
            ->visible(fn (Order $o) => $o->status !== $status)
            ->requiresConfirmation($status === 'cancelled')
            ->action(function (Order $o) use ($status) {
                $o->update(['status' => $status]);   // OrderObserver отправит push покупателю
                Notification::make()->title('Статус: ' . Order::STATUSES[$status])->success()->send();
                $this->refreshFormData(['status']);
            });

        return [
            $to('confirmed', 'Подтвердить', 'info', 'heroicon-o-check'),
            $to('ready', 'Готов к выдаче', 'success', 'heroicon-o-gift'),
            $to('done', 'Выдан', 'gray', 'heroicon-o-check-badge'),
            $to('cancelled', 'Отменить', 'danger', 'heroicon-o-x-mark'),
        ];
    }
}
