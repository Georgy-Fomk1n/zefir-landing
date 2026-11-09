<?php

use App\Models\DeliveryZone;
use App\Services\Auth\Totp;
use App\Services\Loyalty\QrToken;
use Illuminate\Validation\ValidationException;

it('TOTP совпадает с эталоном RFC 6238', function () {
    // RFC 6238, приложение B: секрет "12345678901234567890" (base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), T=59 → 94287082 → 6 знаков 287082
    expect(Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59))->toBe('287082')
        ->and(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59))->toBeTrue()
        ->and(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 59))->toBeFalse();
});

it('QR-токен проверяет подпись, тип и срок', function () {
    $qr = new QrToken;
    $t = $qr->issue('loyalty', 42)['token'];
    expect($qr->verify($t, 'loyalty'))->toBe(42);

    expect(fn () => $qr->verify($t, 'subscription'))->toThrow(ValidationException::class);
    [$payload, $sig] = explode('.', $t);
    expect(fn () => $qr->verify($payload.'.'.strrev($sig), 'loyalty'))->toThrow(ValidationException::class);

    $this->travel(10)->minutes();
    expect(fn () => $qr->verify($t, 'loyalty'))->toThrow(ValidationException::class);
});

it('зона доставки определяет попадание точки в многоугольник', function () {
    $zone = new DeliveryZone(['polygon' => [[55.86, 37.47], [55.86, 37.56], [55.90, 37.56], [55.90, 37.47]], 'price' => 30000, 'free_from' => 300000]);
    expect($zone->contains(55.880223, 37.51184))->toBeTrue()
        ->and($zone->contains(55.75, 37.62))->toBeFalse()
        ->and($zone->costFor(100000))->toBe(30000)
        ->and($zone->costFor(300000))->toBe(0);
});
