<?php

namespace App\Services\Loyalty;

use Illuminate\Validation\ValidationException;

/**
 * Подписанный короткоживущий токен для QR карты лояльности и абонемента.
 * Формат: base64url(json{t,id,exp,n}).base64url(hmac_sha256). Ключ — APP_KEY.
 * Скриншот QR перестаёт работать через qr_ttl_minutes, подделка без APP_KEY невозможна.
 */
class QrToken
{
    public function issue(string $type, int $id, ?int $ttlMinutes = null): array
    {
        $exp = now()->addMinutes($ttlMinutes ?? config('shop.loyalty.qr_ttl_minutes'))->getTimestamp();
        $payload = self::b64(json_encode(['t' => $type, 'id' => $id, 'exp' => $exp, 'n' => bin2hex(random_bytes(4))]));

        return ['token' => $payload.'.'.self::b64($this->sign($payload)), 'expiresAt' => date(DATE_ATOM, $exp)];
    }

    /** @return int идентификатор (пользователя или абонемента) */
    public function verify(string $token, string $expectedType): int
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2 || ! hash_equals(self::b64($this->sign($parts[0])), $parts[1])) {
            throw ValidationException::withMessages(['token' => 'QR-код недействителен']);
        }
        $data = json_decode(self::unb64($parts[0]), true);
        if (! is_array($data) || ($data['t'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages(['token' => 'QR-код другого типа']);
        }
        if (($data['exp'] ?? 0) < now()->getTimestamp()) {
            throw ValidationException::withMessages(['token' => 'QR-код устарел, попросите обновить экран']);
        }

        return (int) $data['id'];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'qr|'.$payload, (string) config('app.key'), true);
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
