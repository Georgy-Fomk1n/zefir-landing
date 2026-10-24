<?php

namespace App\Enums;

use App\Exceptions\InvalidTransition;

/**
 * Конечный автомат на enum: каждый статус перечисляет, куда из него можно перейти.
 */
trait HasTransitions
{
    /** @return list<self> */
    abstract public function next(): array;

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function assertTransitionTo(self $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidTransition(static::class, $this->value, $to->value);
        }
    }

    public function isFinal(): bool
    {
        return $this->next() === [];
    }
}
