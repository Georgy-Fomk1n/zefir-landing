<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\PushSubscription;
use App\Notifications\Channels\PushChannel;
use App\Notifications\DeliveryAssigned;
use App\Notifications\NewOrderForStaff;
use App\Notifications\OrderStatusNotification;
use App\Services\Delivery\DeliveryService;
use App\Services\Push\WebPush;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Ключи «браузера» для подписки: приватный остаётся в тесте, чтобы расшифровать сообщение как user agent. */
function browserKeys(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

    return ['key' => $key, 'public' => WebPush::publicPoint(openssl_pkey_get_details($key)['ec']), 'auth' => random_bytes(16)];
}

/** Расшифровка RFC 8291 на стороне браузера */
function decryptAsBrowser(string $body, array $ua): string
{
    $salt = substr($body, 0, 16);
    $idlen = ord($body[20]);
    $asPublic = substr($body, 21, $idlen);
    $cipher = substr($body, 21 + $idlen);
    $secret = openssl_pkey_derive(WebPush::publicKeyFromPoint($asPublic), $ua['key'], 32);
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0".$ua['public'].$asPublic, $ua['auth']);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));

    return rtrim($plain, "\x02");
}

beforeEach(function () {
    $keys = WebPush::createVapidKeys();
    config(['shop.push.public_key' => $keys['publicKey'], 'shop.push.private_key' => $keys['privateKey'], 'shop.push.subject' => 'mailto:test@example.test']);
});

it('шифрует сообщение по RFC 8291 так, что браузер его расшифровывает', function () {
    $ua = browserKeys();
    $body = app(WebPush::class)->encrypt('{"title":"Заказ готов"}', $ua['public'], $ua['auth']);
    expect(unpack('N', substr($body, 16, 4))[1])->toBe(4096)
        ->and(decryptAsBrowser($body, $ua))->toBe('{"title":"Заказ готов"}');
});

it('отклоняет некорректные ключи подписки', function () {
    app(WebPush::class)->encrypt('x', str_repeat('a', 65), random_bytes(16));
})->throws(RuntimeException::class);

it('подписывает VAPID JWT ключом ES256, проверяемым публичным ключом', function () {
    $jwt = app(WebPush::class)->vapidJwt('https://fcm.googleapis.com');
    [$h, $p, $s] = explode('.', $jwt);
    $claims = json_decode(WebPush::unb64($p), true);
    expect($claims['aud'])->toBe('https://fcm.googleapis.com')->and($claims['sub'])->toBe('mailto:test@example.test');

    $raw = WebPush::unb64($s);
    expect(strlen($raw))->toBe(64);
    // r||s → DER для openssl_verify
    $int = fn ($x) => (ord(ltrim($x, "\0")[0] ?? "\0") > 0x7F ? "\0" : '').ltrim($x, "\0");
    [$r, $sv] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
    $seq = "\x02".chr(strlen($r)).$r."\x02".chr(strlen($sv)).$sv;
    $der = "\x30".chr(strlen($seq)).$seq;
    $pub = WebPush::publicKeyFromPoint(WebPush::unb64(config('shop.push.public_key')));
    expect(openssl_verify("{$h}.{$p}", $der, $pub, OPENSSL_ALGO_SHA256))->toBe(1);
});

it('отправляет push с заголовками VAPID и удаляет подписку, которой больше нет', function () {
    $ua = browserKeys();
    $user = makeUser();
    $sub = PushSubscription::create([
        'user_id' => $user->id, 'scope' => 'customer', 'endpoint' => 'https://push.example.test/abc',
        'endpoint_hash' => hash('sha256', 'https://push.example.test/abc'),
        'public_key' => WebPush::b64($ua['public']), 'auth_token' => WebPush::b64($ua['auth']),
    ]);
    Http::fakeSequence('push.example.test/*')->push(null, 201)->push(null, 410);

    expect(app(WebPush::class)->send($sub, ['title' => 'Привет', 'body' => 'Тест']))->toBeTrue();
    Http::assertSent(function ($r) use ($ua) {
        return str_starts_with($r->header('Authorization')[0], 'vapid t=')
            && $r->header('Content-Encoding')[0] === 'aes128gcm'
            && json_decode(decryptAsBrowser($r->body(), $ua), true)['title'] === 'Привет';
    });
    expect($sub->fresh()->last_used_at)->not->toBeNull();

    expect(app(WebPush::class)->send($sub, ['title' => 'x', 'body' => 'y']))->toBeFalse()
        ->and(PushSubscription::query()->count())->toBe(0);
});

it('не отправляет без ключей VAPID', function () {
    config(['shop.push.public_key' => null]);
    Http::fake();
    $sub = new PushSubscription(['endpoint' => 'https://push.example.test/x']);
    expect(app(WebPush::class)->send($sub, ['title' => 'x', 'body' => 'y']))->toBeFalse();
    Http::assertNothingSent();
});

it('сохраняет подписку покупателя и сотрудника в своей области и отдаёт публичный ключ', function () {
    $payload = ['endpoint' => 'https://push.example.test/1', 'keys' => ['p256dh' => 'BKey', 'auth' => 'auth']];
    $this->getJson('/api/push/key')->assertOk()->assertJsonPath('publicKey', config('shop.push.public_key'));

    $customer = makeUser();
    $this->postJson('/api/account/push', $payload, asCustomer($customer))->assertCreated();
    expect(PushSubscription::query()->first())->scope->toBe('customer')->user_id->toBe($customer->id);

    $courier = makeUser(Role::Courier);
    $this->postJson('/api/staff/push', $payload, asStaff($courier))->assertCreated();
    expect(PushSubscription::query()->count())->toBe(1) // тот же endpoint — одна подписка, перепривязана
        ->and(PushSubscription::query()->first())->scope->toBe('staff')->user_id->toBe($courier->id);

    $this->postJson('/api/account/push', ['endpoint' => 'http://insecure.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']], asCustomer())
        ->assertUnprocessable();
    $this->deleteJson('/api/staff/push', ['endpoint' => 'https://push.example.test/1'], asStaff($courier))->assertNoContent();
    expect(PushSubscription::query()->count())->toBe(0);
    $this->postJson('/api/account/push', $payload)->assertUnauthorized();
});

it('канал отправляет только на устройства нужной области', function () {
    $user = makeUser(Role::Seller, Role::Customer);
    foreach (['customer', 'staff'] as $i => $scope) {
        $ua = browserKeys();
        PushSubscription::create(['user_id' => $user->id, 'scope' => $scope, 'endpoint' => "https://push.example.test/{$i}",
            'endpoint_hash' => hash('sha256', "https://push.example.test/{$i}"), 'public_key' => WebPush::b64($ua['public']), 'auth_token' => WebPush::b64($ua['auth'])]);
    }
    Http::fake(['push.example.test/*' => Http::response(null, 201)]);
    seedCatalog();
    $order = Order::factory()->create(['user_id' => $user->id]);

    app(PushChannel::class)->send($user, new NewOrderForStaff($order));
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->url() === 'https://push.example.test/1');
});

it('уведомляет продавцов о новом оплаченном заказе и курьера о назначении', function () {
    Notification::fake();
    seedCatalog();
    $seller = makeUser(Role::Seller);
    $admin = makeUser(Role::Admin);
    $courier = makeUser(Role::Courier);
    $customer = makeUser();
    $order = Order::factory()->create(['user_id' => $customer->id, 'fulfilment' => 'courier']);

    $order->transitionTo(OrderStatus::Paid);
    Notification::assertSentTo([$seller, $admin], NewOrderForStaff::class);
    Notification::assertNotSentTo($courier, NewOrderForStaff::class);
    Notification::assertSentTo($customer, OrderStatusNotification::class, fn ($n, $channels) => in_array(PushChannel::class, $channels, true));

    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready] as $s) {
        $order->refresh()->transitionTo($s);
    }
    app(DeliveryService::class)->assign($order->refresh(), $courier, $seller);
    Notification::assertSentTo($courier, DeliveryAssigned::class, function (DeliveryAssigned $n) use ($courier) {
        $msg = $n->toPush($courier);

        return $msg['scope'] === 'staff' && str_contains($msg['body'], $n->delivery->order->number) && ! str_contains($msg['body'], 'Ольга');
    });
});

it('формирует push покупателю со ссылкой на заказ', function () {
    seedCatalog();
    $order = Order::factory()->create();
    $msg = (new OrderStatusNotification($order, 'готов'))->toPush(makeUser());
    expect($msg)->title->toBe("Заказ № {$order->number}")->body->toBe('Готов')->scope->toBe('customer')
        ->and($msg['url'])->toEndWith("/account/orders/{$order->id}");
});
