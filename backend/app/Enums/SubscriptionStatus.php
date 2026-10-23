<?php

namespace App\Enums;

/**
 * Абонемент: pending (ждёт первой оплаты) → active → (продление успешно) active
 * active → past_due (списание не прошло, идут повторы) → grace (повторы исчерпаны, период отсрочки) → expired
 * Отмена автопродления не прерывает оплаченный период: active → cancelled → expired по окончании.
 */
enum SubscriptionStatus: string
{
    use HasTransitions;

    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Expired],
            self::Active => [self::Active, self::PastDue, self::Cancelled, self::Expired],
            self::PastDue => [self::Active, self::Grace, self::Cancelled],
            self::Grace => [self::Active, self::Expired, self::Cancelled],
            self::Cancelled => [self::Expired],
            self::Expired => [],
        };
    }

    /** Можно ли погашать абонемент по QR */
    public function isUsable(): bool
    {
        return in_array($this, [self::Active, self::PastDue, self::Grace, self::Cancelled], true);
    }
}
