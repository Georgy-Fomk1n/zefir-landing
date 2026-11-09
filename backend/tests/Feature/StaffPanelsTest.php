<?php

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Events\CatalogChanged;
use App\Models\Category;
use App\Models\CourierShift;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\SeoPage;
use App\Models\SubscriptionPlan;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedCatalog();
    $this->adminUser = makeUser(Role::Admin);
    $this->admin = asStaff($this->adminUser);
    $this->seller = asStaff(makeUser(Role::Seller));
});

/** Оплаченный заказ из корзины через API */
function paidOrder(string $fulfilment = 'pickup', int $qty = 2): Order
{
    $slot = TimeSlot::create(['type' => $fulfilment === 'courier' ? 'courier' : 'pickup', 'date' => now('Europe/Moscow')->addDays(2)->toDateString(),
        'starts_at' => '12:00:00', 'ends_at' => '13:00:00', 'capacity' => 10]);
    $variant = ProductVariant::query()->where('sku', 'ZP-NABOR-4')->first();
    DeliveryZone::query()->firstOrCreate(['name' => 'Зона'], ['polygon' => [[55, 37], [55, 38], [56, 38], [56, 37]], 'price' => 30000]);
    test()->postJson('/api/orders', [
        'lines' => [['variantId' => $variant->id, 'qty' => $qty]], 'name' => 'Ольга', 'phone' => '+79991112233', 'fulfilment' => $fulfilment,
        'slotId' => in_array($fulfilment, ['pickup', 'courier'], true) ? $slot->id : null,
        'address' => $fulfilment === 'pickup' ? null : ['text' => 'Москва, Базовская 15', 'lat' => 55.5, 'lng' => 37.5], 'consent' => true,
    ])->assertCreated();
    app(PaymentService::class)->apply(Payment::query()->latest('id')->first(), PaymentStatus::Paid);

    return Order::query()->latest('id')->first();
}

function advance(Order $order, OrderStatus ...$to): void
{
    foreach ($to as $s) {
        $order->refresh()->transitionTo($s);
    }
}

// ── Админ: каталог ──────────────────────────────────────────────
it('изменяет товар: при смене цены пересборка немедленная, при снятии с продажи — тоже', function () {
    Event::fake([CatalogChanged::class]);
    $kofe = Product::query()->where('slug', 'kofe')->with('variants')->first();
    $payload = ['category_id' => $kofe->category_id, 'slug' => 'kofe', 'name' => 'Кофе', 'description' => 'Новое описание', 'is_active' => true,
        'variants' => [['id' => $kofe->variants[0]->id, 'sku' => 'ZP-KOFE-1', 'name' => '1 шт.', 'price' => 46000, 'stock' => 5]]];

    $this->putJson("/api/staff/admin/products/{$kofe->id}", $payload, $this->admin)->assertOk()->assertJsonPath('data.description', 'Новое описание');
    Event::assertDispatched(CatalogChanged::class, fn ($e) => $e->priceChanged && in_array('/catalog/vkusy/kofe/', $e->paths, true));

    // Вариант, убранный из формы, снимается с продажи, а не удаляется
    $payload['variants'] = [['sku' => 'ZP-KOFE-2', 'name' => '2 шт.', 'price' => 90000, 'stock' => 5]];
    $this->putJson("/api/staff/admin/products/{$kofe->id}", $payload, $this->admin)->assertOk();
    expect(ProductVariant::query()->where('sku', 'ZP-KOFE-1')->value('is_active'))->toBeFalse();

    $this->deleteJson("/api/staff/admin/products/{$kofe->id}", [], $this->admin)->assertNoContent();
    expect($kofe->fresh()->is_active)->toBeFalse();
    Event::assertDispatched(CatalogChanged::class, fn ($e) => $e->deleted);
    $this->getJson('/api/staff/admin/products', $this->admin)->assertOk()->assertJsonCount(8, 'data');
});

it('сохраняет категорию с SEO и проверяет slug', function () {
    Event::fake([CatalogChanged::class]);
    $cat = Category::query()->where('slug', 'vkusy')->first();
    $this->putJson("/api/staff/admin/categories/{$cat->id}", ['slug' => 'vkusy-new', 'name' => 'Вкусы', 'seo_title' => 'Новый title'], $this->admin)
        ->assertOk()->assertJsonPath('data.seo_title', 'Новый title');
    Event::assertDispatched(CatalogChanged::class, fn ($e) => $e->priceChanged && in_array('/catalog/vkusy/', $e->paths, true));
    $this->postJson('/api/staff/admin/categories', ['slug' => 'Плохо', 'name' => 'X'], $this->admin)->assertUnprocessable();
    $this->postJson('/api/staff/admin/categories', ['slug' => 'novaya', 'name' => 'Новая'], $this->admin)->assertCreated();
    $this->getJson('/api/staff/admin/categories', $this->admin)->assertJsonCount(6, 'data');
});

// ── Админ: справочники ──────────────────────────────────────────
it('ведёт промокоды с проверкой процента и сроков', function () {
    $this->postJson('/api/staff/admin/promo-codes', ['code' => 'х', 'type' => 'percent', 'value' => 150], $this->admin)->assertUnprocessable();
    $this->postJson('/api/staff/admin/promo-codes', ['code' => 'осень', 'type' => 'fixed', 'value' => 20000, 'starts_at' => '2026-11-01', 'ends_at' => '2026-10-01'], $this->admin)
        ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    $id = $this->postJson('/api/staff/admin/promo-codes', ['code' => 'осень', 'type' => 'fixed', 'value' => 20000], $this->admin)->assertOk()->json('data.id');
    expect(PromoCode::query()->find($id)->code)->toBe('ОСЕНЬ');
    $this->putJson("/api/staff/admin/promo-codes/{$id}", ['code' => 'ОСЕНЬ', 'type' => 'fixed', 'value' => 30000, 'is_active' => false], $this->admin)->assertOk();
    $this->getJson('/api/staff/admin/promo-codes', $this->admin)->assertJsonPath('data.0.value', 30000);
});

it('ведёт зоны доставки и тарифы абонементов, отдаёт зоны в экспорт', function () {
    Cache::flush();
    $this->postJson('/api/staff/admin/zones', ['name' => 'Север', 'polygon' => [[1, 2]], 'price' => 100], $this->admin)->assertUnprocessable();
    $this->postJson('/api/staff/admin/zones', ['name' => 'Север', 'polygon' => [[55, 37], [55, 38], [56, 38]], 'price' => 30000, 'eta_minutes' => 90], $this->admin)->assertOk();
    $this->getJson('/api/staff/admin/zones', $this->admin)->assertJsonCount(1, 'data');
    $this->getJson('/api/catalog/export')->assertJsonPath('deliveryZones.0.etaText', 'от 1 ч 30 мин');

    $this->postJson('/api/staff/admin/plans', ['name' => 'Кофе', 'description' => 'д', 'price' => 50, 'period_days' => 30, 'daily_limit' => 1], $this->admin)->assertUnprocessable();
    $id = $this->postJson('/api/staff/admin/plans', ['name' => 'Кофе', 'description' => 'д', 'price' => 300000, 'period_days' => 30, 'daily_limit' => 2], $this->admin)->assertOk()->json('data.id');
    $this->putJson("/api/staff/admin/plans/{$id}", ['name' => 'Кофе+', 'description' => 'д', 'price' => 300000, 'period_days' => 30, 'daily_limit' => 2], $this->admin)->assertOk();
    $this->getJson('/api/staff/admin/plans', $this->admin)->assertJsonPath('data.0.name', 'Кофе+');
    expect(SubscriptionPlan::query()->count())->toBe(1);
});

it('сохраняет SEO-поля контентной страницы и отдаёт их в экспорт', function () {
    Cache::flush();
    $this->postJson('/api/staff/admin/seo-pages', ['path' => 'dostavka'], $this->admin)->assertUnprocessable();
    $this->postJson('/api/staff/admin/seo-pages', ['path' => '/dostavka-i-oplata/', 'seo_title' => 'Доставка пирожных', 'seo_text' => 'Текст'], $this->admin)->assertOk();
    $this->postJson('/api/staff/admin/seo-pages', ['path' => '/dostavka-i-oplata/', 'seo_title' => 'Доставка пирожных 2'], $this->admin)->assertOk();
    expect(SeoPage::query()->count())->toBe(1);
    $this->getJson('/api/staff/admin/seo-pages', $this->admin)->assertJsonPath('data.0.seo_title', 'Доставка пирожных 2');
    $this->getJson('/api/catalog/export')->assertJsonPath('seoPages.0.path', '/dostavka-i-oplata/')->assertJsonPath('seoPages.0.title', 'Доставка пирожных 2');
});

it('управляет слотами', function () {
    $slot = TimeSlot::create(['type' => 'pickup', 'date' => '2026-10-27', 'starts_at' => '12:00:00', 'ends_at' => '13:00:00', 'capacity' => 5]);
    $this->getJson('/api/staff/admin/slots?date=2026-10-27', $this->admin)->assertJsonCount(1, 'data');
    $this->patchJson("/api/staff/admin/slots/{$slot->id}", ['capacity' => 0, 'is_active' => false], $this->admin)->assertOk();
    expect($slot->fresh())->capacity->toBe(0)->is_active->toBeFalse();
    $this->postJson('/api/staff/admin/slots/generate', [], $this->admin)->assertOk()->assertJsonStructure(['created']);
});

it('ведёт сотрудников: роли, запрет снять с себя админа, сброс 2FA, блокировка завершает сеанс', function () {
    $this->getJson('/api/staff/admin/users?staff=1', $this->admin)->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/staff/admin/users?q=nobody-here', $this->admin)->assertJsonCount(0, 'data');
    $this->putJson("/api/staff/admin/users/{$this->adminUser->id}", ['name' => 'А', 'email' => $this->adminUser->email, 'roles' => ['seller']], $this->admin)
        ->assertUnprocessable();
    $this->postJson('/api/staff/admin/users', ['name' => 'К', 'email' => 'k@example.test', 'password' => 'short', 'roles' => ['courier']], $this->admin)->assertUnprocessable();

    $courier = makeUser(Role::Courier);
    $courierHeaders = asStaff($courier);
    $this->putJson("/api/staff/admin/users/{$courier->id}", ['name' => 'К', 'email' => $courier->email, 'roles' => ['courier', 'seller'], 'is_active' => false], $this->admin)
        ->assertOk()->assertJsonPath('data.roles', ['courier', 'seller']);
    $this->getJson('/api/staff/auth/me', $courierHeaders)->assertUnauthorized();

    $this->adminUser->forceFill(['two_factor_secret' => 'X', 'two_factor_confirmed_at' => now()])->save();
    $this->postJson("/api/staff/admin/users/{$this->adminUser->id}/reset-2fa", [], $this->admin)->assertNoContent();
    expect($this->adminUser->fresh()->hasTwoFactor())->toBeFalse();
    $this->getJson('/api/staff/admin/password-suggestion', asStaff(User::query()->find($this->adminUser->id)))->assertOk()->assertJsonStructure(['password']);
});

// ── Продавец ────────────────────────────────────────────────────
it('ведёт заказ самовывоза по очереди до выдачи и запрещает лишние переходы', function () {
    $order = paidOrder();
    $date = $order->slot->date->format('Y-m-d');
    $this->getJson("/api/staff/seller/queue?date={$date}", $this->seller)->assertOk()->assertJsonPath('data.0.number', $order->number)
        ->assertJsonPath('data.0.contact.phone', '+79991112233');
    $url = "/api/staff/seller/orders/{$order->id}/status";
    $this->postJson($url, ['status' => 'ready'], $this->seller)->assertUnprocessable(); // перепрыгнуть нельзя
    $this->postJson($url, ['status' => 'pending_payment'], $this->seller)->assertUnprocessable();
    foreach (['accepted', 'preparing', 'ready'] as $s) {
        $this->postJson($url, ['status' => $s], $this->seller)->assertOk();
    }
    $this->postJson($url, ['status' => 'shipped'], $this->seller)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson($url, ['status' => 'handed_to_courier'], $this->seller)->assertUnprocessable();
    $this->postJson($url, ['status' => 'completed', 'comment' => 'Выдан'], $this->seller)->assertOk()->assertJsonPath('data.status', 'completed');
});

it('не закрывает курьерский заказ «выдан» в точке', function () {
    $order = paidOrder('courier');
    advance($order, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $this->postJson("/api/staff/seller/orders/{$order->id}/status", ['status' => 'completed'], $this->seller)->assertUnprocessable();
});

it('отменяет оплаченный заказ с возвратом денег, остатков, слота', function () {
    $order = paidOrder();
    $variant = ProductVariant::query()->where('sku', 'ZP-NABOR-4')->first();
    expect($variant->stock)->toBe(8);
    $this->postJson("/api/staff/seller/orders/{$order->id}/cancel", [], $this->seller)->assertUnprocessable();
    $this->postJson("/api/staff/seller/orders/{$order->id}/cancel", ['reason' => 'Клиент передумал'], $this->seller)->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($variant->fresh()->stock)->toBe(10)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->slot->fresh()->booked)->toBe(0);
});

it('доска доставок: автоназначение только курьеру на смене', function () {
    $order = paidOrder('courier');
    advance($order, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['auto' => true], $this->seller)->assertStatus(422);

    $idle = makeUser(Role::Courier);
    $onShift = makeUser(Role::Courier);
    CourierShift::create(['courier_id' => $onShift->id, 'started_at' => now()]);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['auto' => true], $this->seller)->assertOk()->assertJsonPath('data.courier.id', $onShift->id);
    $date = $order->slot->date->format('Y-m-d');
    $this->getJson("/api/staff/seller/deliveries?date={$date}", $this->seller)->assertOk()->assertJsonCount(1, 'data.orders')->assertJsonCount(2, 'data.couriers');

    // Переназначение после принятия курьером возвращает статус «назначена»
    $this->postJson("/api/staff/courier/deliveries/{$order->delivery->id}/status", ['status' => 'accepted'], asStaff($onShift))->assertOk();
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $idle->id], $this->seller)->assertOk();
    expect($order->delivery->fresh())->status->toBe(DeliveryStatus::Assigned)->courier_id->toBe($idle->id);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => makeUser(Role::Seller)->id], $this->seller)->assertUnprocessable();
    $this->postJson("/api/staff/seller/orders/{$order->id}/returned", [], $this->seller)->assertUnprocessable();
});

it('не создаёт доставку для заказа самовывоза и запрещает переназначение после выезда', function () {
    $pickup = paidOrder();
    advance($pickup, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $courier = makeUser(Role::Courier);
    $this->postJson("/api/staff/seller/orders/{$pickup->id}/assign", ['courierId' => $courier->id], $this->seller)->assertUnprocessable();

    $order = paidOrder('courier');
    advance($order, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], $this->seller)->assertOk();
    $h = asStaff($courier);
    foreach (['accepted', 'picked_up'] as $s) {
        $this->postJson("/api/staff/courier/deliveries/{$order->delivery->id}/status", ['status' => $s], $h)->assertOk();
    }
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => makeUser(Role::Courier)->id], $this->seller)->assertUnprocessable();
    $this->postJson("/api/staff/seller/orders/{$pickup->id}/returned", [], $this->seller)->assertNotFound();
});

// ── Курьер ──────────────────────────────────────────────────────
it('принимает оплату при получении на открытой смене и сверяет кассу', function () {
    config(['shop.delivery.cash_on_delivery' => true]);
    Storage::fake('local');
    $order = paidOrder('courier');
    advance($order, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $courier = makeUser(Role::Courier);
    $h = asStaff($courier);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], $this->seller);
    $d = $order->delivery()->first();
    $d->update(['cod_amount' => 210000]); // заказ к оплате при получении
    foreach (['accepted', 'picked_up', 'in_transit'] as $s) {
        $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => $s], $h)->assertOk();
    }
    $url = "/api/staff/courier/deliveries/{$d->id}/status";
    $this->postJson($url, ['status' => 'delivered', 'codMethod' => 'cash', 'codAmount' => 210000], $h)->assertUnprocessable()->assertJsonValidationErrors('shift');
    $this->postJson('/api/staff/courier/shift/start', [], $h)->assertOk();
    $this->postJson($url, ['status' => 'delivered', 'codMethod' => 'cash', 'codAmount' => 100], $h)->assertUnprocessable()->assertJsonValidationErrors('cod');
    $this->post($url, ['status' => 'delivered', 'codMethod' => 'cash', 'codAmount' => 210000, 'photo' => UploadedFile::fake()->image('proof.jpg')], $h + ['Accept' => 'application/json'])->assertOk();

    $d->refresh();
    expect($d->cod_method)->toBe('cash')->and($d->proof_photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($d->proof_photo_path);
    $this->getJson('/api/staff/courier/today', $h)->assertJsonPath('data.shift.cash', 210000);
    $this->postJson('/api/staff/courier/shift/end', [], $h)->assertJsonPath('data.delivered', 1)->assertJsonPath('data.cash', 210000);
});

it('запрещает вручение неоплаченного заказа, если оплата при получении выключена', function () {
    $order = paidOrder('courier');
    advance($order, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready);
    $courier = makeUser(Role::Courier);
    $h = asStaff($courier);
    $this->postJson("/api/staff/seller/orders/{$order->id}/assign", ['courierId' => $courier->id], $this->seller);
    $d = $order->delivery()->first();
    $d->update(['cod_amount' => 1000]);
    foreach (['accepted', 'picked_up', 'in_transit'] as $s) {
        $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => $s], $h);
    }
    $this->postJson("/api/staff/courier/deliveries/{$d->id}/status", ['status' => 'delivered'], $h)->assertUnprocessable()->assertJsonValidationErrors('cod');
});
