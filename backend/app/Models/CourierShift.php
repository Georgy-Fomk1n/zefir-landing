<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierShift extends Model
{
    protected $fillable = ['courier_id', 'started_at', 'ended_at', 'cash_collected', 'card_collected'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
