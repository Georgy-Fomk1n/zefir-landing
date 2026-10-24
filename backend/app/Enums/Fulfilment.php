<?php

namespace App\Enums;

enum Fulfilment: string
{
    case Pickup = 'pickup';
    case Courier = 'courier';
    case Cdek = 'cdek';
    case RussianPost = 'russian_post';

    public function needsSlot(): bool
    {
        return $this === self::Pickup || $this === self::Courier;
    }

    public function needsAddress(): bool
    {
        return $this !== self::Pickup;
    }
}
