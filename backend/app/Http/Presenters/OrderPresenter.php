<?php

namespace App\Http\Presenters;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;

/** Заказ в формате packages/shared/src/types.ts → Order */
class OrderPresenter
{
    public static function make(Order $o, ?User $viewer = null, bool $public = false): array
    {
        $o->loadMissing(['items', 'history', 'slot', 'delivery.history']);
        $reviewed = $viewer && $o->status === OrderStatus::Completed
            ? Review::query()->where('order_id', $o->id)->pluck('product_id')->all()
            : [];
        $event = fn ($h) => ['from' => $h->from, 'to' => $h->to, 'at' => $h->created_at->toIso8601String(), 'comment' => $public ? null : $h->comment];

        $data = [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status->value,
            'fulfilment' => $o->fulfilment->value,
            'items' => $o->items->map(fn ($i) => [
                'variantId' => $i->product_variant_id,
                'productId' => $i->product_id,
                'productName' => $i->product_name,
                'variantName' => $i->variant_name,
                'price' => $i->price,
                'qty' => $i->qty,
                'reviewable' => $viewer !== null && $o->status === OrderStatus::Completed && $i->product_id && ! in_array($i->product_id, $reviewed, true),
            ])->all(),
            'subtotal' => $o->subtotal,
            'discount' => $o->discount,
            'pointsSpent' => $o->points_spent,
            'deliveryCost' => $o->delivery_cost,
            'total' => $o->total,
            'paymentStatus' => $o->payment_status->value,
            'slot' => $o->slot?->toWindow(),
            'delivery' => $o->delivery ? [
                'status' => $o->delivery->status->value,
                'address' => $public ? null : $o->addressText(),
                'history' => $o->delivery->history->map($event)->all(),
            ] : null,
            'history' => $o->history->map($event)->all(),
            'trackingUrl' => $public ? null : $o->trackingUrl(),
            'trackingToken' => $public ? null : $o->tracking_token,
            'externalTracking' => $o->external_tracking,
            'createdAt' => $o->created_at->toIso8601String(),
        ];
        if ($public) {
            // Публичное отслеживание: без состава, сумм и персональных данных
            return array_intersect_key($data, array_flip(['number', 'status', 'paymentStatus', 'fulfilment', 'slot', 'delivery', 'history', 'externalTracking']));
        }

        return $data;
    }

    /** Для служебных панелей: с контактами клиента */
    public static function staff(Order $o): array
    {
        return self::make($o) + [
            'contact' => ['name' => $o->contact_name, 'phone' => $o->contact_phone, 'email' => $o->contact_email],
            'address' => $o->address,
            'comment' => $o->comment,
            'courier' => $o->delivery?->courier?->only(['id', 'name']),
            'zone' => $o->zone?->only(['id', 'name']),
        ];
    }
}
