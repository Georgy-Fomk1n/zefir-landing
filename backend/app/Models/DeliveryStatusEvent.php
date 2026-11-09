<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'delivery_status_history';

    protected $fillable = ['delivery_id', 'from', 'to', 'user_id', 'comment', 'lat', 'lng', 'happened_at', 'client_action_id'];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }
}
