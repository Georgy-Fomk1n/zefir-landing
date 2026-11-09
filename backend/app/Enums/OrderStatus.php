<?php

namespace App\Enums;

enum OrderStatus: string
{
    use HasTransitions;

    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case HandedToCourier = 'handed_to_courier';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function next(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            // Готовый заказ: выдача в точке, передача курьеру или отправка службой доставки
            self::Ready => [self::Completed, self::HandedToCourier, self::Shipped, self::Cancelled],
            self::HandedToCourier => [self::Completed, self::Ready, self::Cancelled],
            self::Shipped => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    /** Статусы, которые меняет продавец в очереди заказов ко времени */
    public static function sellerManaged(): array
    {
        return [self::Accepted, self::Preparing, self::Ready, self::Completed, self::HandedToCourier, self::Shipped];
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Ожидает оплаты',
            self::Paid => 'Оплачен',
            self::Accepted => 'Принят',
            self::Preparing => 'Готовится',
            self::Ready => 'Готов',
            self::HandedToCourier => 'Передан курьеру',
            self::Shipped => 'Отправлен',
            self::Completed => 'Выдан',
            self::Cancelled => 'Отменён',
        };
    }
}
