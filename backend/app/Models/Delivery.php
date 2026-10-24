<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Delivery extends Model
{
    protected $fillable = ['order_id', 'courier_id', 'status', 'route_position', 'proof_photo_path', 'fail_reason', 'cod_amount', 'cod_method', 'delivered_at'];

    protected function casts(): array
    {
        return ['status' => DeliveryStatus::class, 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(DeliveryStatusEvent::class)->orderBy('id');
    }

    /**
     * @param  array{comment?: ?string, lat?: ?float, lng?: ?float, happened_at?: ?string, client_action_id?: ?string}  $meta
     */
    public function transitionTo(DeliveryStatus $to, ?User $by, array $meta = []): void
    {
        DB::transaction(function () use ($to, $by, $meta) {
            /** @var self $fresh */
            $fresh = self::query()->lockForUpdate()->findOrFail($this->id);
            $fresh->status->assertTransitionTo($to);
            $from = $fresh->status;
            $fresh->status = $to;
            if ($to === DeliveryStatus::Delivered) {
                $fresh->delivered_at = $meta['happened_at'] ?? now();
            }
            $fresh->save();
            $fresh->history()->create([
                'from' => $from->value, 'to' => $to->value, 'user_id' => $by?->id,
                'comment' => $meta['comment'] ?? null, 'lat' => $meta['lat'] ?? null, 'lng' => $meta['lng'] ?? null,
                'happened_at' => $meta['happened_at'] ?? now(), 'client_action_id' => $meta['client_action_id'] ?? null,
            ]);
            $this->setRawAttributes($fresh->getAttributes(), true);
        });
        event(new DeliveryStatusChanged($this, $to));
    }
}
