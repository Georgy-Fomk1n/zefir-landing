<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\TimeSlot;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedCatalog();
    // Вторник 10:00 по Москве; слот на 14:00 того же дня (подготовка 120 мин)
    Carbon::setTestNow(Carbon::parse('2026-10-27 10:00', 'Europe/Moscow'));
    $this->slot = TimeSlot::create(['type' => 'pickup', 'date' => '2026-10-27', 'starts_at' => '14:00:00', 'ends_at' => '15:00:00', 'capacity' => 2]);
    $this->kofe = ProductVariant::query()->where('sku', 'ZP-KOFE-1')->first();
});

function orderPayload(array $over = []): array
{
    return array_replace([
        'lines' => [['variantId' => test()->kofe->id, 'qty' => 2]],
        'name' => 'Мария', 'phone' => '+7 (999) 123-45-67', 'email' => 'maria@example.test',
        'fulfilment' => 'pickup', 'slotId' => test()->slot->id, 'consent' => true,
    ], $over);
}

it('экспортирует каталог для пререндера и ищет товары', function () {
    $this->getJson('/api/catalog/export')->assertOk()
        ->assertJsonCount(5, 'categories')->assertJsonCount(8, 'products')
        ->assertJsonPath('products.0.variants.0.price', 45000);
    $this->getJson('/api/catalog/products?q=фисташ')->assertOk()->assertJsonPath('data.0.slug', 'fistashka');
    $this->getJson('/api/catalog/products?category=pp-bez-sahara&sort=price_asc')->assertOk()->assertJsonPath('data.0.slug', 'pp-banan');
    $this->getJson('/api/catalog/products/nope')->assertNotFound();
});

it('считает корзину по серверным ценам, а не по присланным', function () {
    $this->postJson('/api/cart/quote', ['lines' => [['variantId' => $this->kofe->id, 'qty' => 3, 'price' => 1]], 'fulfilment' => 'pickup'])
        ->assertOk()->assertJsonPath('data.subtotal', 135000)->assertJsonPath('data.total', 135000);
});

it('оформляет заказ: резерв остатков, бронь слота, ссылка на оплату', function () {
    $res = $this->postJson('/api/orders', orderPayload())->assertCreated();
    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->total)->toBe(90000)
        ->and($order->contact_phone)->toBe('+79991234567')
        ->and($this->kofe->fresh()->reserved)->toBe(2)
        ->and($this->slot->fresh()->booked)->toBe(1)
        ->and($res->json('payment.url'))->toContain('/api/payments/fake/');
});

it('не даёт заказать больше остатка и в заполненный слот', function () {
    $this->postJson('/api/orders', orderPayload(['lines' => [['variantId' => $this->kofe->id, 'qty' => 21]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
    $this->postJson('/api/orders', orderPayload())->assertCreated();
    $this->postJson('/api/orders', orderPayload())->assertCreated();
    $this->postJson('/api/orders', orderPayload())->assertUnprocessable()->assertJsonValidationErrors('slotId');
});

it('не даёт выбрать слот раньше времени подготовки', function () {
    $early = TimeSlot::create(['type' => 'pickup', 'date' => '2026-10-27', 'starts_at' => '11:00:00', 'ends_at' => '12:00:00', 'capacity' => 5]);
    $this->postJson('/api/orders', orderPayload(['slotId' => $early->id]))->assertUnprocessable()->assertJsonValidationErrors('slotId');
    $this->getJson('/api/slots?type=pickup')->assertOk()->assertJsonMissing(['id' => $early->id])->assertJsonFragment(['id' => $this->slot->id]);
});

it('применяет промокод с лимитами и категориями', function () {
    PromoCode::create(['code' => 'ПП10', 'type' => 'percent', 'value' => 10, 'per_user_limit' => 1,
        'category_ids' => [Category::query()->where('slug', 'vkusy')->value('id')]]);
    $quote = $this->postJson('/api/cart/quote', ['lines' => [['variantId' => $this->kofe->id, 'qty' => 2]], 'fulfilment' => 'pickup', 'promoCode' => 'пп10'])->assertOk();
    expect($quote->json('data.discount'))->toBe(9000)->and($quote->json('data.total'))->toBe(81000);

    $this->postJson('/api/orders', orderPayload(['promoCode' => 'ПП10']))->assertCreated();
    // Повторно тем же телефоном — лимит на покупателя
    $this->postJson('/api/orders', orderPayload(['promoCode' => 'ПП10']))->assertUnprocessable()->assertJsonValidationErrors('promoCode');

    $banan = ProductVariant::query()->where('sku', 'ZP-PP-BANAN-1')->first();
    $this->postJson('/api/cart/quote', ['lines' => [['variantId' => $banan->id, 'qty' => 1]], 'fulfilment' => 'pickup', 'promoCode' => 'ПП10'])
        ->assertJsonPath('data.promoError', 'Промокод не действует на товары в корзине');
});

it('списывает баллы с ограничением доли и возвращает их при отмене', function () {
    $user = makeUser(Role::Customer);
    LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'adjust', 'amount' => 1000]);
    $h = asCustomer($user);

    // 30% от 900 ₽ = 270 баллов
    $this->postJson('/api/cart/quote', ['lines' => [['variantId' => $this->kofe->id, 'qty' => 2]], 'fulfilment' => 'pickup', 'points' => 500], $h)
        ->assertJsonPath('data.maxPoints', 270)->assertJsonPath('data.pointsSpent', 27000);
    $this->postJson('/api/orders', orderPayload(['points' => 500]), $h)->assertUnprocessable()->assertJsonValidationErrors('points');
    $this->postJson('/api/orders', orderPayload(['points' => 270]), $h)->assertCreated()->assertJsonPath('data.total', 63000);
    expect(app(LoyaltyService::class)->balance($user))->toBe(730);

    // Не оплачен вовремя → отмена, резерв, слот и баллы возвращаются
    $this->travel(31)->minutes();
    $this->artisan('orders:release-expired')->assertSuccessful();
    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($this->kofe->fresh()->reserved)->toBe(0)
        ->and($this->slot->fresh()->booked)->toBe(0)
        ->and(app(LoyaltyService::class)->balance($user))->toBe(1000);
});

it('принимает вебхук оплаты один раз и проверяет подпись', function () {
    $this->postJson('/api/orders', orderPayload())->assertCreated();
    $payment = Payment::query()->firstOrFail();
    $body = json_encode(['eventId' => 'evt-1', 'paymentId' => $payment->provider_payment_id, 'status' => 'paid']);

    $this->call('POST', '/api/webhooks/payments', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => 'bad'], $body)->assertStatus(400);
    $ok = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::sign($body)];
    $this->call('POST', '/api/webhooks/payments', [], [], [], $ok, $body)->assertOk();
    $this->call('POST', '/api/webhooks/payments', [], [], [], $ok, $body)->assertOk();

    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->history()->where('to', 'paid')->count())->toBe(1)
        ->and($this->kofe->fresh()->stock)->toBe(18)
        ->and($this->kofe->fresh()->reserved)->toBe(0);
});

it('возвращает деньги, если оплата пришла после отмены по таймауту', function () {
    $this->postJson('/api/orders', orderPayload())->assertCreated();
    $this->travel(31)->minutes();
    $this->artisan('orders:release-expired');
    $payment = Payment::query()->firstOrFail();
    app(PaymentService::class)->apply($payment, PaymentStatus::Paid);
    expect(Order::query()->first()->status)->toBe(OrderStatus::Cancelled)->and($this->kofe->fresh()->stock)->toBe(20);
});

it('отдаёт публичное отслеживание без персональных данных', function () {
    $this->postJson('/api/orders', orderPayload())->assertCreated();
    $order = Order::query()->firstOrFail();
    $this->getJson("/api/track/{$order->tracking_token}")->assertOk()
        ->assertJsonPath('data.number', $order->number)
        ->assertJsonMissingPath('data.total')->assertJsonMissingPath('data.items')
        ->assertDontSee('Мария');
    $this->getJson('/api/track/'.str_repeat('x', 40))->assertNotFound();
});

it('не показывает чужой заказ (IDOR)', function () {
    $owner = makeUser(Role::Customer);
    $this->postJson('/api/orders', orderPayload(), asCustomer($owner))->assertCreated();
    $order = Order::query()->firstOrFail();
    $this->getJson("/api/account/orders/{$order->id}", asCustomer())->assertForbidden();
    $this->getJson("/api/account/orders/{$order->id}", asCustomer($owner))->assertOk();
});

it('разрешает отзыв только покупателю выданного заказа', function () {
    $user = makeUser(Role::Customer);
    $h = asCustomer($user);
    $this->postJson('/api/orders', orderPayload(), $h)->assertCreated();
    $order = Order::query()->firstOrFail();
    $review = ['productId' => $this->kofe->product_id, 'rating' => 5, 'text' => 'Очень вкусно, рекомендую!'];
    $this->postJson("/api/account/orders/{$order->id}/reviews", $review, $h)->assertUnprocessable();

    app(PaymentService::class)->apply(Payment::query()->first(), PaymentStatus::Paid);
    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $s) {
        $order->refresh()->transitionTo($s);
    }
    $this->postJson("/api/account/orders/{$order->id}/reviews", $review, $h)->assertCreated();
    $this->postJson("/api/account/orders/{$order->id}/reviews", $review, $h)->assertUnprocessable();
    // Баллы начислены после выдачи: 5% от 900 ₽
    expect(app(LoyaltyService::class)->balance($user))->toBe(45);
});
