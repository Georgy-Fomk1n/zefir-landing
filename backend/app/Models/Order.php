<?php

namespace App\Models;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'user_id', 'status', 'fulfilment', 'contact_name', 'contact_phone', 'contact_email', 'address',
        'slot_id', 'zone_id', 'promo_code_id', 'subtotal', 'discount', 'points_spent', 'delivery_cost', 'total', 'payment_status',
        'comment', 'reserved_until', 'tracking_token', 'external_tracking', 'loyalty_awarded_at'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfilment' => Fulfilment::class,
            'payment_status' => PaymentStatus::class,
            'address' => 'array',
            'reserved_until' => 'datetime',
            'loyalty_awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'slot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** Смена статуса через конечный автомат; каждый переход пишется в историю с автором. */
    public function transitionTo(OrderStatus $to, ?User $by = null, ?string $comment = null): void
    {
        DB::transaction(function () use ($to, $by, $comment) {
            /** @var self $fresh */
            $fresh = self::query()->lockForUpdate()->findOrFail($this->id);
            $fresh->status->assertTransitionTo($to);
            $from = $fresh->status;
            $fresh->status = $to;
            $fresh->save();
            $fresh->history()->create(['from' => $from->value, 'to' => $to->value, 'user_id' => $by?->id, 'comment' => $comment]);
            $this->setRawAttributes($fresh->getAttributes(), true);
        });
        event(new OrderStatusChanged($this, $to));
    }

    public function addressText(): ?string
    {
        return $this->address['text'] ?? null;
    }

    public function trackingUrl(): string
    {
        return rtrim(config('shop.public_url'), '/').'/track/?t='.$this->tracking_token;
    }
}
