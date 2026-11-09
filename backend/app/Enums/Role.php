<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Seller = 'seller';
    case Courier = 'courier';
    case Admin = 'admin';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /** @return list<self> */
    public static function staff(): array
    {
        return [self::Seller, self::Courier, self::Admin];
    }
}
