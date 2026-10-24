<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    use HasTransitions;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    public function next(): array
    {
        return match ($this) {
            self::Assigned => [self::Accepted],
            self::Accepted => [self::PickedUp, self::Assigned],
            self::PickedUp => [self::InTransit, self::Failed],
            self::InTransit => [self::Delivered, self::Failed],
            self::Failed => [self::Returned, self::InTransit],
            self::Delivered, self::Returned => [],
        };
    }

    /** Активная доставка: курьеру показывается телефон клиента (при политике active_only) */
    public function isActive(): bool
    {
        return in_array($this, [self::Accepted, self::PickedUp, self::InTransit], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Назначена',
            self::Accepted => 'Принята курьером',
            self::PickedUp => 'Курьер забрал заказ',
            self::InTransit => 'В пути',
            self::Delivered => 'Доставлена',
            self::Failed => 'Не удалось вручить',
            self::Returned => 'Возврат',
        };
    }
}
