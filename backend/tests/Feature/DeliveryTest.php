<?php

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\TimeSlot;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedCatalog();
    Carbon::setTestNow(Carbon::parse('2026-10-27 10:00', 'Europe/Moscow'));
    $this->zone = DeliveryZone::create(['name' => 'Север', 'polygon' => [[55.86, 37.47], [55.86, 37.56], [55.90, 37.56], [55.90, 37.47]],
        'price' => 30000, 'min_order' => 100000, 'free_from' => 300000, 'eta_minutes' => 90]);
    $this->slot = TimeSlot::create(['type' => 'courier', 'date' => '2026-10-27', 'starts_at' => '15:00:00', 'ends_at' => '16:00:00', 'capacity' => 1]);
    $this->variant = ProductVariant::query()->where('sku', 'ZP-NABOR-4')->first();
});

function courierOrder(array $address = ['text' => 'Москва, ул. Базовская, 15', 'lat' => 55.88, 'lng' => 37.51]): Order
{
    test()->postJson('/api/orders', [
        'lines' => [['variantId' => test()->variant->id, 'qty' => 2]], 'name' => 'Ольга', 'phone' => '+79991112233',
        'fulfilment' => 'courier', 'slotId' => test()->slot->id, 'address' => $address + ['entrance' => '2', 'floor' => '5'], 'consent' => true,
    ])->assertCreated();
    $order = Order::query()->latest('id')->firstOrFail();
    app(PaymentService::class)->apply(Payment::query()->latest('id')->first(), PaymentStatus::Paid);
    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready] as $s) {
        $order->refresh()->transitionTo($s);
    }

    return $order->refresh();
}

it('считает доставку по зоне: стоимость, минимальная сумма, бесплатно от порога', function () {
    $in = ['text' => 'ул. Базовская', 'lat' => 55.88, 'lng' => 37.51];
    $quote = fn ($qty, $addr) => $this->postJson('/api/cart/quote', ['lines' => [['variantId' => $this->variant->id, 'qty' => $qty]], 'fulfilment' => 'courier', 'address' => $addr])->json('data');

    expect($quote(1, $in)['delivery']['available'])->toBeFalse(); // 900 ₽ < минимальных 1000 ₽
    expect($quote(2, $in))->deliveryCost->toBe(30000)->total->toBe(210000);
    expect($quote(4, $in))->deliveryCost->toBe(0); // 3600 ₽ ≥ 3000 ₽
    $out = $quote(2, ['text' => 'Красная площадь', 'lat' => 55.7539, 'lng' => 37.6208]);
    expect($out['delivery']['available'])->toBeFalse()->and($out['delivery']['message'])->toContain('вне зоны');
});

it('блокирует оформление на адрес вне зоны и соблюдает лимит интервала', function () {
    $this->postJson('/api/orders', [
        'lines' => [['variantId' => $this->variant->id, 'qty' => 2]], 'name' => 'Ольга', 'phone' => '+79991112233',
        'fulfilment' => 'courier', 'slotId' => $this->slot->id, 'address' => ['text' => 'Центр', 'lat' => 55.75, 'lng' => 37.62], 'consent' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('address');

    courierOrder();
    $this->postJson('/api/orders', [
        'lines' => [['variantId' => $this->variant->id, 'qty' => 2]], 'name' => 'Ольга', 'phone' => '+79991112233',
        'fulfilment' => 'courier', 'slotId' => $this->slot->id, 'address' => ['text' => 'x', 'lat' => 55.88, 'lng' => 37.51], 'consent' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('slotId');
});

it('курьер видит только свои доставки (нет доступа к чужим)', function () {
    $order = courierOrder();
    $seller = makeUser(Role::Seller);
    $mine = makeUser(Role::Courier);
    $other = makeUser(Role::Courier);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $mine->id], asStaff($seller))->assertOk();
    $delivery = $order->refresh()->delivery;

    expect($order->status)->toBe(OrderStatus::HandedToCourier);
    $this->getJson('/api/staff/courier/today', asStaff($other))->assertOk()->assertJsonCount(0, 'data.deliveries');
    $this->getJson("/api/staff/courier/deliveries/{$delivery->id}", asStaff($other))->assertForbidden();
    $this->postJson("/api/staff/courier/deliveries/{$delivery->id}/status", ['status' => 'accepted'], asStaff($other))->assertForbidden();
    $this->getJson('/api/staff/courier/today', asStaff($mine))->assertOk()->assertJsonCount(1, 'data.deliveries')
        ->assertJsonPath('data.deliveries.0.entrance', '2');
    // Курьеру недоступны панели продавца и админа
    $this->getJson('/api/staff/seller/queue', asStaff($mine))->assertForbidden();
});

it('проводит доставку по конечному автомату и показывает телефон только в активной доставке', function () {
    $order = courierOrder();
    $courier = makeUser(Role::Courier);
    $h = asStaff($courier);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], asStaff(makeUser(Role::Seller)))->assertOk();
    $d = $order->refresh()->delivery;
    $url = "/api/staff/courier/deliveries/{$d->id}/status";

    $this->getJson("/api/staff/courier/deliveries/{$d->id}", $h)->assertJsonPath('data.phone', null);
    $this->postJson($url, ['status' => 'delivered'], $h)->assertUnprocessable(); // нельзя перепрыгнуть
    $this->postJson($url, ['status' => 'accepted'], $h)->assertOk()->assertJsonPath('data.phone', '+79991112233');
    $this->postJson($url, ['status' => 'picked_up', 'lat' => 55.88, 'lng' => 37.51], $h)->assertOk();
    $this->postJson($url, ['status' => 'in_transit'], $h)->assertOk();
    $this->postJson($url, ['status' => 'failed'], $h)->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson($url, ['status' => 'delivered', 'clientActionId' => 'a1'], $h)->assertOk()->assertJsonPath('data.phone', null);
    // Повтор из офлайн-очереди не ломает историю
    $this->postJson($url, ['status' => 'delivered', 'clientActionId' => 'a1'], $h)->assertOk();

    $d->refresh();
    expect($d->status)->toBe(DeliveryStatus::Delivered)
        ->and($d->history()->count())->toBe(5)
        ->and($d->history()->where('to', 'picked_up')->value('lat'))->not->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Completed);
});

it('возвращает невручённый заказ в точку', function () {
    $order = courierOrder();
    $courier = makeUser(Role::Courier);
    $seller = makeUser(Role::Seller);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], asStaff($seller));
    $d = $order->refresh()->delivery;
    foreach (['accepted', 'picked_up', 'in_transit'] as $s) {
        $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => $s], asStaff($courier))->assertOk();
    }
    $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => 'failed', 'reason' => 'Не открыли дверь'], asStaff($courier))->assertOk();
    $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => 'returned'], asStaff($courier))->assertUnprocessable();
    $this->postJson("/api/staff/seller/orders/{$order->id}/returned", [], asStaff($seller))->assertOk();
    expect($d->refresh()->status)->toBe(DeliveryStatus::Returned)->and($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('ведёт смену курьера и итог дня', function () {
    $courier = makeUser(Role::Courier);
    $h = asStaff($courier);
    $this->postJson('/api/staff/courier/shift/start', [], $h)->assertOk();
    $this->getJson('/api/staff/courier/today', $h)->assertJsonPath('data.shift.cash', 0);
    $this->postJson('/api/staff/courier/shift/end', [], $h)->assertOk()->assertJsonStructure(['data' => ['delivered', 'failed', 'cash', 'card']]);
});

it('маскирует телефон при политике masked', function () {
    config(['shop.delivery.courier_phone_policy' => 'masked']);
    $order = courierOrder();
    $courier = makeUser(Role::Courier);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], asStaff(makeUser(Role::Seller)));
    $this->getJson("/api/staff/courier/deliveries/{$order->refresh()->delivery->id}", asStaff($courier))->assertJsonPath('data.phone', '+7 *** ***-22-33');
});
