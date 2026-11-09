<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRedemption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['subscription_id', 'seller_id', 'redeemed_on'];

    protected function casts(): array
    {
        return ['redeemed_on' => 'date'];
    }
}
