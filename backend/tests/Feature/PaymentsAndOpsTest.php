<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Jobs\DispatchStaticRebuild;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\OrderStatusNotification;
use App\Services\Catalog\IndexNow;
use App\Services\Catalog\StaticRebuild;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TochkaPaymentProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** RSA-ключ «Точки» и подписанный им JWT вебхука */
function tochkaJwt(array $claims, ?OpenSSLAsymmetricKey $key = null, string $alg = 'RS256'): array
{
    $key ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $data = b64url(json_encode(['alg' => $alg, 'typ' => 'JWT'])).'.'.b64url(json_encode($claims));
    openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);

    return ['jwt' => $data.'.'.b64url($sig), 'public' => openssl_pkey_get_details($key)['key'], 'key' => $key];
}

function configureTochka(string $publicKey = 'x'): void
{
    config(['shop.payments.tochka' => [
        'base_url' => 'https://enter.tochka.test/uapi', 'token' => 'jwt-token', 'customer_code' => '300000092', 'merchant_id' => '200000000001',
        'webhook_public_key' => $publicKey, 'recurring' => false,
    ]]);
}

it('Точка: создаёт платёжную ссылку, с чеком при фискализации', function () {
    configureTochka();
    Http::fake(['enter.tochka.test/*' => Http::response(['Data' => ['operationId' => 'op-1', 'paymentLink' => 'https://pay.tochka.test/op-1']])]);
    $order = Order::factory()->create();
    $payment = Payment::create(['payable_type' => 'order', 'payable_id' => $order->id, 'provider' => 'tochka', 'idempotency_key' => 'idem-1', 'amount' => 123450, 'status' => 'pending']);
    $p = new TochkaPaymentProvider;

    $init = $p->create($payment, 'Заказ', 'https://site/ok', 'https://site/fail', false);
    expect($init->providerPaymentId)->toBe('op-1')->and($init->url)->toBe('https://pay.tochka.test/op-1');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/acquiring/v1.0/payments') && $r['Data']['amount'] === '1234.50'
        && $r['Data']['paymentLinkId'] === 'idem-1' && $r['Data']['paymentMode'] === ['card', 'sbp'] && $r->header('Authorization')[0] === 'Bearer jwt-token');

    config(['shop.payments.fiscalization' => 'tochka_receipt']);
    $p->create($payment, 'Заказ', 'https://site/ok', 'https://site/fail', true, [['name' => 'Кофе', 'price' => 45000, 'qty' => 2]]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments_with_receipt') && $r['Data']['Items'][0]['amount'] === '450.00' && $r['Data']['paymentMode'] === ['card']);
});

it('Точка: падает без ключей и при ошибке API', function () {
    config(['shop.payments.tochka.token' => null]);
    $payment = new Payment(['amount' => 100, 'idempotency_key' => 'k']);
    expect(fn () => (new TochkaPaymentProvider)->create($payment, 'x', 'a', 'b', false))->toThrow(RuntimeException::class);
    configureTochka();
    Http::fake(['*' => Http::response(['error' => 'x'], 400)]);
    expect(fn () => (new TochkaPaymentProvider)->create($payment, 'x', 'a', 'b', false))->toThrow(RuntimeException::class);
});

it('Точка: проверяет подпись JWT вебхука и разбирает статус', function () {
    $signed = tochkaJwt(['webhookType' => 'acquiringInternetPayment', 'operationId' => 'op-1', 'status' => 'APPROVED', 'maskedPan' => '4242', 'cardToken' => 'ct']);
    configureTochka($signed['public']);
    $req = fn (string $body) => Request::create('/api/webhooks/payments', 'POST', [], [], [], [], $body);
    $res = (new TochkaPaymentProvider)->parseWebhook($req($signed['jwt']));
    expect($res->status)->toBe(PaymentStatus::Paid)->and($res->providerPaymentId)->toBe('op-1')
        ->and($res->eventId)->toBe('op-1:APPROVED')->and($res->cardTitle)->toBe('Карта 4242')->and($res->cardToken)->toBe('ct');

    // Подпись чужим ключом
    $forged = tochkaJwt(['webhookType' => 'acquiringInternetPayment', 'operationId' => 'op-1', 'status' => 'APPROVED']);
    expect(fn () => (new TochkaPaymentProvider)->parseWebhook($req($forged['jwt'])))->toThrow(InvalidWebhook::class);
    // Не тот тип вебхука, не тот алгоритм, мусор
    $other = tochkaJwt(['webhookType' => 'incomingPayment'], $signed['key']);
    expect(fn () => (new TochkaPaymentProvider)->parseWebhook($req($other['jwt'])))->toThrow(InvalidWebhook::class);
    $none = tochkaJwt(['webhookType' => 'acquiringInternetPayment'], $signed['key'], 'none');
    expect(fn () => (new TochkaPaymentProvider)->parseWebhook($req($none['jwt'])))->toThrow(InvalidWebhook::class);
    expect(fn () => (new TochkaPaymentProvider)->parseWebhook($req('garbage')))->toThrow(InvalidWebhook::class);

    foreach (['REFUNDED' => PaymentStatus::Refunded, 'EXPIRED' => PaymentStatus::Failed, 'CREATED' => PaymentStatus::Pending] as $status => $expected) {
        $s = tochkaJwt(['webhookType' => 'acquiringInternetPayment', 'operationId' => 'op-2', 'status' => $status], $signed['key']);
        expect((new TochkaPaymentProvider)->parseWebhook($req($s['jwt']))->status)->toBe($expected);
    }
    // PEM без заголовков (как копируют из кабинета)
    $bare = preg_replace('/-----[^-]+-----|\s/', '', $signed['public']);
    configureTochka($bare);
    expect((new TochkaPaymentProvider)->parseWebhook($req($signed['jwt']))->status)->toBe(PaymentStatus::Paid);
});

it('Точка: статус, возврат и рекуррентное списание', function () {
    configureTochka();
    Http::fake([
        'enter.tochka.test/uapi/acquiring/v1.0/payments/op-1' => Http::response(['Data' => ['Operation' => [['status' => 'APPROVED']]]]),
        'enter.tochka.test/uapi/acquiring/v1.0/payments/op-2' => Http::response(['Data' => ['status' => 'EXPIRED']]),
        'enter.tochka.test/uapi/acquiring/v1.0/payments/op-1/refund' => Http::response(['Data' => ['isRefund' => true]]),
        'enter.tochka.test/uapi/acquiring/v1.0/subscriptions/*' => Http::sequence()->push(['Data' => ['operationId' => 'op-rec']])->whenEmpty(Http::response(null, 500)),
    ]);
    $p = new TochkaPaymentProvider;
    $pay = fn ($id) => new Payment(['provider_payment_id' => $id, 'amount' => 30000]);
    expect($p->fetchStatus($pay('op-1')))->toBe(PaymentStatus::Paid)
        ->and($p->fetchStatus($pay('op-2')))->toBe(PaymentStatus::Failed)
        ->and($p->refund($pay('op-1'), 30000))->toBeTrue()
        ->and($p->supportsRecurring())->toBeFalse();
    expect(fn () => $p->chargeRecurring($pay('op-1'), 'sub-1', 'Продление'))->toThrow(RuntimeException::class);

    config(['shop.payments.tochka.recurring' => true]);
    $payment = $pay('old');
    expect($p->chargeRecurring($payment, 'sub-1', 'Продление'))->toBe(PaymentStatus::Pending)->and($payment->provider_payment_id)->toBe('op-rec');
    expect($p->chargeRecurring($pay('x'), 'sub-1', 'Продление'))->toBe(PaymentStatus::Failed);
});

it('выбирает провайдера из конфига и запрещает тестовый на production', function () {
    config(['shop.payments.provider' => 'tochka']);
    app()->forgetInstance(PaymentProvider::class);
    expect(app(PaymentProvider::class))->toBeInstanceOf(TochkaPaymentProvider::class);
    config(['shop.payments.provider' => 'fake']);
    app()->forgetInstance(PaymentProvider::class);
    app()->detectEnvironment(fn () => 'production');
    expect(fn () => app(PaymentProvider::class))->toThrow(RuntimeException::class);
    app()->detectEnvironment(fn () => 'testing');
});

function placePickupOrder(array $over = []): array
{
    seedCatalog();
    $slot = TimeSlot::create(['type' => 'pickup', 'date' => now('Europe/Moscow')->addDays(2)->toDateString(), 'starts_at' => '12:00:00', 'ends_at' => '13:00:00', 'capacity' => 5]);
    $variant = ProductVariant::query()->where('sku', 'ZP-KOFE-1')->first();

    return test()->postJson('/api/orders', array_replace([
        'lines' => [['variantId' => $variant->id, 'qty' => 1]], 'name' => 'Мария', 'phone' => '89991234567', 'fulfilment' => 'pickup', 'slotId' => $slot->id, 'consent' => true,
    ], $over))->assertCreated()->json();
}

it('тестовая оплата: страница, оплата и отказ по подписанной ссылке', function () {
    $res = placePickupOrder();
    $url = $res['payment']['url'];
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $this->get($path)->assertOk()->assertSee('Тестовая оплата')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $path).'&action=pay')->assertForbidden();
    $this->get($path.'&action=decline')->assertRedirect();
    $order = Order::query()->first();
    expect($order->payment_status)->toBe(PaymentStatus::Failed);
    // Повторная попытка: новая ссылка по токену отслеживания
    $retry = $this->postJson("/api/track/{$order->tracking_token}/pay")->assertOk()->json('payment.url');
    expect($retry)->not->toBe($url);
    $this->getJson("/api/track/{$order->tracking_token}")->assertJsonPath('data.paymentStatus', 'failed');
    $retryPath = parse_url($retry, PHP_URL_PATH).'?'.parse_url($retry, PHP_URL_QUERY);
    $this->get($retryPath.'&action=pay')->assertRedirectContains('paid=1');
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
    // Оплаченный заказ повторно не оплачивается
    $this->postJson("/api/track/{$order->tracking_token}/pay")->assertUnprocessable();
});

it('заказ, полностью оплаченный промокодом, не требует оплаты', function () {
    PromoCode::create(['code' => 'ПОДАРОК', 'type' => 'percent', 'value' => 100]);
    $res = placePickupOrder(['promoCode' => 'подарок']);
    expect($res['data']['total'])->toBe(0)->and($res['payment']['url'])->toContain('/track/?t=')
        ->and(Order::query()->first()->status)->toBe(OrderStatus::Paid)->and(Payment::query()->count())->toBe(0);
});

it('сверка зависших платежей применяет статус провайдера', function () {
    placePickupOrder();
    $payment = Payment::query()->first();
    $this->travel(10)->minutes();
    $fake = Mockery::mock(FakePaymentProvider::class)->makePartial();
    $fake->shouldReceive('fetchStatus')->andReturn(PaymentStatus::Paid);
    app()->instance(PaymentProvider::class, $fake);
    app()->forgetInstance(PaymentService::class);
    $this->artisan('payments:reconcile')->expectsOutputToContain('Сверено платежей: 1')->assertSuccessful();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)->and(Order::query()->first()->status)->toBe(OrderStatus::Paid);
});

it('вебхук для неизвестного платежа помечается обработанным и не падает', function () {
    $body = json_encode(['eventId' => 'e-x', 'paymentId' => 'nope', 'status' => 'paid']);
    Log::spy();
    $this->call('POST', '/api/webhooks/payments', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::sign($body)], $body)->assertOk();
    expect(WebhookEvent::query()->first()->processed_at)->not->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => $m === 'webhook for unknown payment');
});

it('отправляет уведомления в Telegram и SMS (лог) и формирует письмо', function () {
    config(['shop.notifications.telegram_bot_token' => 'bot-token', 'shop.notifications.channels' => ['mail', 'telegram', 'sms']]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    Log::spy();
    $order = Order::factory()->create();
    $user = User::factory()->create(['telegram_chat_id' => '12345', 'phone' => '+79990000000']);
    $n = new OrderStatusNotification($order, 'готов');
    expect($n->via($user))->toBe(['mail', TelegramChannel::class, SmsChannel::class]);

    (new TelegramChannel)->send($user, $n);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'botbot-token/sendMessage') && $r['chat_id'] === '12345' && str_contains($r['text'], $order->number));
    (new SmsChannel)->send($user, $n);
    Log::shouldHaveReceived('info')->withArgs(fn ($m, $ctx) => $m === 'sms' && $ctx['to'] === '+79990000000');

    $mail = $n->toMail($user);
    expect($mail->subject)->toContain($order->number)->and($mail->actionUrl)->toBe($order->trackingUrl());
    // Без токена бота и без телефона — тихо пропускаем
    config(['shop.notifications.telegram_bot_token' => null]);
    (new TelegramChannel)->send($user, $n);
    (new SmsChannel)->send(new User, $n);
    Http::assertSentCount(1);
});

it('IndexNow отправляет URL с ключом и молчит, если выключен', function () {
    Http::fake(['yandex.com/*' => Http::sequence()->push(null, 202)->push(null, 422)->push(null, 200)]);
    expect(app(IndexNow::class)->submit(['/catalog/']))->toBeFalse();
    config(['shop.indexnow.enabled' => true, 'shop.indexnow.key' => 'abcdef123456', 'shop.public_url' => 'https://зефир-и-пряник.рф']);
    expect(app(IndexNow::class)->submit(['/catalog/', '/catalog/']))->toBeTrue();
    Http::assertSent(fn ($r) => $r['key'] === 'abcdef123456' && $r['urlList'] === ['https://зефир-и-пряник.рф/catalog/'] && $r['keyLocation'] === 'https://зефир-и-пряник.рф/abcdef123456.txt');
    expect(app(IndexNow::class)->submit(['/x/']))->toBeFalse();
    $this->artisan('seo:indexnow /catalog/')->assertSuccessful();
});

it('при ошибке GitHub пересборка возвращается в очередь путей', function () {
    config(['shop.rebuild.enabled' => true, 'shop.rebuild.token' => 't']);
    Http::fake(['api.github.com/*' => Http::response(null, 500)]);
    $rebuild = app(StaticRebuild::class);
    Queue::fake();
    $rebuild->request(['/catalog/'], immediate: true);
    expect(fn () => (new DispatchStaticRebuild)->handle($rebuild))->toThrow(RequestException::class);
    expect($rebuild->pullPending())->toBe(['/catalog/']);
    // Нечего пересобирать — запрос не отправляется
    (new DispatchStaticRebuild)->handle($rebuild);
    Http::assertSentCount(1);
    config(['shop.rebuild.enabled' => false]);
    $rebuild->request(['/x/']);
    expect($rebuild->pullPending())->toBe([]);
});

it('выполняет служебные команды', function () {
    $this->artisan('push:vapid')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->assertSuccessful();
    $this->artisan('staff:create-admin owner@example.test Владелец')->expectsOutputToContain('временный пароль')->assertSuccessful();
    expect(User::query()->where('email', 'owner@example.test')->first()->hasRole(Role::Admin))->toBeTrue();
    $this->artisan('staff:create-admin owner@example.test Владелец')->expectsOutputToContain('выдана роль')->assertSuccessful();
    $this->artisan('subscriptions:process')->assertSuccessful();
    $this->artisan('loyalty:expire')->expectsOutputToContain('Сгорело баллов: 0')->assertSuccessful();
    $this->artisan('orders:release-expired')->assertSuccessful();

    $out = sys_get_temp_dir().'/openapi-test.json';
    $this->artisan('api:openapi', ['--output' => $out])->assertSuccessful();
    $doc = json_decode(file_get_contents($out), true);
    expect($doc['openapi'])->toBe('3.1.0')
        ->and($doc['paths'])->toHaveKey('/api/orders')
        ->and($doc['paths']['/api/orders']['post']['requestBody']['content']['application/json']['schema']['properties'])->toHaveKey('consent')
        ->and($doc['paths']['/api/staff/admin/products']['get']['description'])->toContain('Роли: admin');
});

it('отдаёт деградацию health, если БД недоступна', function () {
    config(['cache.default' => 'array']);
    \Illuminate\Support\Facades\DB::shouldReceive('select')->once()->andThrow(new RuntimeException('db down'));
    $this->getJson('/api/health')->assertStatus(503)->assertJsonPath('db', false)->assertJsonPath('status', 'degraded');
});

it('сигнатура ссылки на тестовую оплату живёт час', function () {
    $res = placePickupOrder();
    $this->travel(61)->minutes();
    $path = parse_url($res['payment']['url'], PHP_URL_PATH).'?'.parse_url($res['payment']['url'], PHP_URL_QUERY);
    $this->get($path.'&action=pay')->assertForbidden();
    expect(URL::hasValidSignature(Request::create($res['payment']['url'])))->toBeFalse();
});
