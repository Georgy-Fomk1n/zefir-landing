<?php

namespace App\Http\Controllers\Staff\Courier;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourierTransitionRequest;
use App\Models\CourierShift;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Панель курьера: только назначенные ему доставки и только нужные для доставки данные клиента. */
class CourierController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    public function today(Request $request): JsonResponse
    {
        $tz = config('shop.timezone');
        $list = Delivery::query()->where('courier_id', $request->user()->id)
            ->where(fn ($q) => $q->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit', 'failed'])
                ->orWhere('updated_at', '>=', now($tz)->startOfDay()->utc()))
            ->with(['order.slot'])->get()
            ->sortBy(fn (Delivery $d) => [$d->status->isFinal() ? 1 : 0, $d->order->slot?->date?->format('Y-m-d').' '.$d->order->slot?->starts_at, $d->route_position ?? 999])
            ->values();
        $shift = CourierShift::query()->where('courier_id', $request->user()->id)->whereNull('ended_at')->first();

        return response()->json(['data' => [
            'deliveries' => $list->map($this->deliveries->courierView(...)),
            'shift' => $shift ? ['id' => $shift->id, 'startedAt' => $shift->started_at->toIso8601String(), 'cash' => $shift->cash_collected, 'card' => $shift->card_collected] : null,
            'cashOnDelivery' => (bool) config('shop.delivery.cash_on_delivery'),
        ]]);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        Gate::authorize('deliver', $delivery);

        return response()->json(['data' => $this->deliveries->courierView($delivery->load('order.slot'))]);
    }

    public function transition(CourierTransitionRequest $request, Delivery $delivery): JsonResponse
    {
        Gate::authorize('deliver', $delivery);
        $photo = $request->file('photo')?->store('delivery-proofs/'.now()->format('Y/m'), 'local');
        $delivery = $this->deliveries->courierTransition($delivery, $request->user(), [
            'status' => $request->input('status'),
            'reason' => $request->input('reason'),
            'lat' => $request->input('lat'),
            'lng' => $request->input('lng'),
            'happened_at' => $request->input('happenedAt'),
            'client_action_id' => $request->input('clientActionId'),
            'cod_method' => $request->input('codMethod'),
            'cod_amount' => $request->integer('codAmount'),
            'photo_path' => $photo,
        ]);

        return response()->json(['data' => $this->deliveries->courierView($delivery->load('order.slot'))]);
    }

    public function startShift(Request $request): JsonResponse
    {
        $shift = CourierShift::query()->firstOrCreate(['courier_id' => $request->user()->id, 'ended_at' => null], ['started_at' => now()]);

        return response()->json(['data' => ['id' => $shift->id, 'startedAt' => $shift->started_at->toIso8601String()]]);
    }

    /** Закрытие смены с итогом дня и сверкой кассы */
    public function endShift(Request $request): JsonResponse
    {
        $shift = CourierShift::query()->where('courier_id', $request->user()->id)->whereNull('ended_at')->firstOrFail();
        $shift->update(['ended_at' => now()]);
        $period = Delivery::query()->where('courier_id', $request->user()->id)->whereBetween('updated_at', [$shift->started_at, $shift->ended_at]);

        return response()->json(['data' => [
            'startedAt' => $shift->started_at->toIso8601String(),
            'endedAt' => $shift->ended_at->toIso8601String(),
            'delivered' => (clone $period)->where('status', DeliveryStatus::Delivered->value)->count(),
            'failed' => (clone $period)->whereIn('status', [DeliveryStatus::Failed->value, DeliveryStatus::Returned->value])->count(),
            'cash' => $shift->cash_collected,
            'card' => $shift->card_collected,
        ]]);
    }
}
