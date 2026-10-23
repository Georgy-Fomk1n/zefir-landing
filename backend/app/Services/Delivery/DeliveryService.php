<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CourierShift;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /** Создаёт запись доставки для курьерского заказа (вызывается при передаче готового заказа курьеру). */
    public function createFor(Order $order): Delivery
    {
        if ($order->fulfilment !== Fulfilment::Courier) {
            throw ValidationException::withMessages(['order' => 'Заказ не курьерский']);
        }

        return $order->delivery ?? $order->delivery()->create([
            'status' => DeliveryStatus::Assigned,
            'cod_amount' => $order->payment_status->value === 'paid' ? 0 : $order->total,
        ]);
    }

    /** Ручное назначение/переназначение курьера продавцом или админом. */
    public function assign(Order $order, User $courier, User $by): Delivery
    {
        if (! $courier->hasRole(Role::Courier) || ! $courier->is_active) {
            throw ValidationException::withMessages(['courier_id' => 'Пользователь не курьер']);
        }

        return DB::transaction(function () use ($order, $courier, $by) {
            $delivery = $this->createFor($order);
            if (! in_array($delivery->status, [DeliveryStatus::Assigned, DeliveryStatus::Accepted], true)) {
                throw ValidationException::withMessages(['order' => 'Курьер уже забрал заказ, переназначение невозможно']);
            }
            $reassign = $delivery->courier_id !== null && $delivery->courier_id !== $courier->id;
            $delivery->courier_id = $courier->id;
            $delivery->route_position = (int) Delivery::query()->where('courier_id', $courier->id)
                ->whereIn('status', [DeliveryStatus::Assigned, DeliveryStatus::Accepted])->max('route_position') + 1;
            $delivery->save();
            if ($delivery->status === DeliveryStatus::Accepted && $reassign) {
                $delivery->transitionTo(DeliveryStatus::Assigned, $by, ['comment' => 'Переназначен курьер']);
            } else {
                $delivery->history()->create(['from' => null, 'to' => DeliveryStatus::Assigned->value, 'user_id' => $by->id,
                    'comment' => ($reassign ? 'Переназначен: ' : 'Назначен: ').$courier->name, 'happened_at' => now()]);
            }
            if ($order->status === OrderStatus::Ready) {
                $order->transitionTo(OrderStatus::HandedToCourier, $by, 'Передан курьеру '.$courier->name);
            }

            return $delivery->refresh();
        });
    }

    /** Автоназначение [УТОЧНИТЬ]: курьер на открытой смене с наименьшим числом активных доставок. */
    public function autoAssign(Order $order, User $by): ?Delivery
    {
        $onShift = CourierShift::query()->whereNull('ended_at')->pluck('courier_id');
        $courier = User::query()->whereIn('id', $onShift)->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasRole(Role::Courier))
            ->sortBy(fn (User $u) => Delivery::query()->where('courier_id', $u->id)
                ->whereIn('status', [DeliveryStatus::Assigned, DeliveryStatus::Accepted, DeliveryStatus::PickedUp, DeliveryStatus::InTransit])->count())
            ->first();

        return $courier ? $this->assign($order, $courier, $by) : null;
    }

    /**
     * Переход статуса курьером. Идемпотентно по client_action_id (офлайн-очередь может прислать действие дважды).
     *
     * @param  array{status: string, reason?: ?string, lat?: ?float, lng?: ?float, happened_at?: ?string, client_action_id?: ?string, cod_method?: ?string, cod_amount?: ?int, photo_path?: ?string}  $data
     */
    public function courierTransition(Delivery $delivery, User $courier, array $data): Delivery
    {
        if (! empty($data['client_action_id']) && $delivery->history()->where('client_action_id', $data['client_action_id'])->exists()) {
            return $delivery;
        }
        $to = DeliveryStatus::from($data['status']);
        if ($to === DeliveryStatus::Failed && empty($data['reason'])) {
            throw ValidationException::withMessages(['reason' => 'Укажите причину, почему не удалось вручить']);
        }
        if ($to === DeliveryStatus::Returned) {
            throw ValidationException::withMessages(['status' => 'Возврат оформляет продавец или администратор']);
        }

        return DB::transaction(function () use ($delivery, $courier, $data, $to) {
            if ($to === DeliveryStatus::Failed) {
                $delivery->fail_reason = $data['reason'];
            }
            if ($to === DeliveryStatus::Delivered) {
                if (! empty($data['photo_path'])) {
                    $delivery->proof_photo_path = $data['photo_path'];
                }
                if ($delivery->cod_amount > 0) {
                    if (! config('shop.delivery.cash_on_delivery')) {
                        throw ValidationException::withMessages(['cod' => 'Заказ не оплачен, а оплата при получении отключена']);
                    }
                    if (! in_array($data['cod_method'] ?? null, ['cash', 'card'], true) || (int) ($data['cod_amount'] ?? 0) !== $delivery->cod_amount) {
                        throw ValidationException::withMessages(['cod' => 'Укажите способ и полную сумму оплаты при получении']);
                    }
                    $delivery->cod_method = $data['cod_method'];
                    $this->addToShiftCash($courier, $delivery->cod_amount, $data['cod_method']);
                }
            }
            $delivery->save();
            $delivery->transitionTo($to, $courier, [
                'comment' => $data['reason'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'happened_at' => $data['happened_at'] ?? null,
                'client_action_id' => $data['client_action_id'] ?? null,
            ]);
            $order = $delivery->order;
            if ($to === DeliveryStatus::Delivered && $order->status === OrderStatus::HandedToCourier) {
                $order->transitionTo(OrderStatus::Completed, $courier, 'Доставлен');
            }

            return $delivery->refresh();
        });
    }

    /** Возврат невручённого заказа в точку (продавец/админ). */
    public function markReturned(Delivery $delivery, User $by): void
    {
        DB::transaction(function () use ($delivery, $by) {
            $delivery->transitionTo(DeliveryStatus::Returned, $by, ['comment' => 'Заказ вернулся в точку']);
            if ($delivery->order->status === OrderStatus::HandedToCourier) {
                $delivery->order->transitionTo(OrderStatus::Ready, $by, 'Возврат от курьера');
            }
        });
    }

    private function addToShiftCash(User $courier, int $amount, string $method): void
    {
        $shift = CourierShift::query()->where('courier_id', $courier->id)->whereNull('ended_at')->latest('started_at')->first();
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => 'Откройте смену, чтобы принимать оплату']);
        }
        $shift->increment($method === 'cash' ? 'cash_collected' : 'card_collected', $amount);
    }

    /**
     * Данные клиента для курьера: только нужное для доставки. Телефон — по политике COURIER_PHONE_POLICY:
     * active_only — только в активной доставке; masked — маска до активной; always — всегда.
     */
    public function courierView(Delivery $delivery): array
    {
        $order = $delivery->order;
        $policy = config('shop.delivery.courier_phone_policy');
        $phone = match (true) {
            $policy === 'always' => $order->contact_phone,
            $delivery->status->isActive() => $order->contact_phone,
            $policy === 'masked' && ! $delivery->status->isFinal() => self::maskPhone($order->contact_phone),
            default => null,
        };
        $address = $order->address ?? [];

        return [
            'id' => $delivery->id,
            'orderNumber' => $order->number,
            'status' => $delivery->status->value,
            'routePosition' => $delivery->route_position,
            'clientName' => $delivery->status->isFinal() ? null : $order->contact_name,
            'phone' => $phone,
            'address' => $address['text'] ?? null,
            'entrance' => $address['entrance'] ?? null,
            'floor' => $address['floor'] ?? null,
            'intercom' => $address['intercom'] ?? null,
            'lat' => $address['lat'] ?? null,
            'lng' => $address['lng'] ?? null,
            'comment' => $order->comment,
            'interval' => $order->slot?->toWindow(),
            'total' => $order->total,
            'codAmount' => $delivery->cod_amount,
            'paid' => $delivery->cod_amount === 0,
            'failReason' => $delivery->fail_reason,
        ];
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 4 ? '+7 *** ***-'.substr($digits, -4, 2).'-'.substr($digits, -2) : '***';
    }
}
