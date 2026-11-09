<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Запись журнала баллов. Изменение и удаление запрещены: исправления — новой записью типа adjust. */
class LoyaltyTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'amount', 'order_id', 'created_by', 'comment', 'idempotency_key', 'expires_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Журнал баллов неизменяем'));
        static::deleting(fn () => throw new LogicException('Журнал баллов неизменяем'));
    }
}
