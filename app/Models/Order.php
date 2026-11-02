<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['slot_date' => 'date'];
    }

    public const STATUSES = [
        'new' => 'Новый', 'confirmed' => 'Подтверждён', 'ready' => 'Готов к выдаче', 'done' => 'Выдан', 'cancelled' => 'Отменён',
    ];

    /** Какие статусы уведомляют покупателя и что в уведомлении */
    public function statusPush(): ?array
    {
        $n = $this->number();

        return match ($this->status) {
            'confirmed' => ["Заказ № $n подтверждён", 'Спасибо! Начинаем собирать ваш заказ.'],
            'ready' => ["Заказ № $n готов", $this->shipping === 'courier' ? 'Курьер скоро выедет к вам.' : 'Можно забирать: ' . config('shop.address')],
            'cancelled' => ["Заказ № $n отменён", 'Если это ошибка, позвоните нам: ' . config('shop.phone')],
            default => null,
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function number(): string
    {
        return (string) (1000 + $this->id);
    }

    public function slotLabel(): string
    {
        return $this->slot_date->translatedFormat('j F') . ', ' . $this->slot_hour . ':00–' . ($this->slot_hour + 1) . ':00';
    }
}
