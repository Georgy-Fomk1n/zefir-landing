<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Fulfilment;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\CourierShift;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Доска доставок (админ и продавец): по зонам и интервалам, назначение и переназначение курьеров. */
class DeliveryBoardController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    public function board(Request $request): JsonResponse
    {
        $date = $request->query('date', now(config('shop.timezone'))->toDateString());
        $orders = Order::query()->where('fulfilment', Fulfilment::Courier->value)
            ->whereHas('slot', fn ($s) => $s->whereDate('date', $date))
            ->whereNotIn('status', ['pending_payment', 'cancelled'])
            ->with(['items', 'history', 'slot', 'zone', 'delivery.courier', 'delivery.history'])->get();
        $courierIds = DB::table('user_roles')->where('role', Role::Courier->value)->pluck('user_id');
        $couriers = User::query()->whereIn('id', $courierIds)->where('is_active', true)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'onShift' => CourierShift::query()->where('courier_id', $u->id)->whereNull('ended_at')->exists(),
            'active' => Delivery::query()->where('courier_id', $u->id)->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->count(),
        ]);

        return response()->json(['data' => [
            'orders' => $orders->map(OrderPresenter::staff(...))->values(),
            'couriers' => $couriers,
        ]]);
    }

    public function assign(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['courierId' => ['nullable', 'integer'], 'auto' => ['boolean']]);
        if (! empty($data['auto'])) {
            $delivery = $this->deliveries->autoAssign($order, $request->user());
            abort_if(! $delivery, 422, 'Нет курьеров на смене');
        } else {
            $this->deliveries->assign($order, User::query()->findOrFail($data['courierId']), $request->user());
        }

        return response()->json(['data' => OrderPresenter::staff($order->refresh()->load('delivery.courier'))]);
    }

    public function returned(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->delivery, 404);
        $this->deliveries->markReturned($order->delivery, $request->user());

        return response()->json(['data' => OrderPresenter::staff($order->refresh())]);
    }
}
