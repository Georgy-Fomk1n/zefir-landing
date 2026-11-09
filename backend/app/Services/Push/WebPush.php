<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Web Push без внешних библиотек: шифрование RFC 8291 (aes128gcm) и VAPID RFC 8292 на встроенном openssl.
 * Работает в Chrome/Android, Firefox, Safari macOS и iOS/iPadOS 16.4+ (только для сайта, добавленного
 * на экран «Домой» как веб-приложение). Ключи VAPID: `php artisan push:vapid`.
 */
class WebPush
{
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** @return array{publicKey: string, privateKey: string} base64url: публичный — 65 байт (04||x||y), приватный — 32 байта d */
    public static function createVapidKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'publicKey' => self::b64(self::publicPoint($ec)),
            'privateKey' => self::b64(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('shop.push.public_key') && (bool) config('shop.push.private_key');
    }

    /**
     * Отправить уведомление. Возвращает true при успехе; подписка, которой больше нет (404/410), удаляется.
     *
     * @param  array{title: string, body: string, url?: string, tag?: string}  $message
     */
    public function send(PushSubscription $sub, array $message, int $ttl = 86400, string $urgency = 'normal'): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = $this->encrypt($payload, self::unb64($sub->public_key), self::unb64($sub->auth_token));
        $origin = self::origin($sub->endpoint);

        $res = Http::timeout(10)->withHeaders([
            'Authorization' => 'vapid t='.$this->vapidJwt($origin).', k='.config('shop.push.public_key'),
            'Content-Encoding' => 'aes128gcm',
            'TTL' => (string) $ttl,
            'Urgency' => $urgency,
        ])->withBody($body, 'application/octet-stream')->post($sub->endpoint);

        if (in_array($res->status(), [404, 410], true)) {
            $sub->delete();

            return false;
        }
        if (! $res->successful()) {
            Log::warning('web push failed', ['status' => $res->status(), 'host' => parse_url($sub->endpoint, PHP_URL_HOST)]);

            return false;
        }
        $sub->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    /**
     * RFC 8291: тело aes128gcm (заголовок salt||rs||idlen||keyid + шифротекст) для подписки с ключами браузера.
     */
    public function encrypt(string $plaintext, string $uaPublic, string $authSecret, ?string $salt = null): string
    {
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) !== 16) {
            throw new RuntimeException('Некорректные ключи подписки');
        }
        $ephemeral = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublic = self::publicPoint(openssl_pkey_get_details($ephemeral)['ec']);
        $ecdhSecret = openssl_pkey_derive(self::publicKeyFromPoint($uaPublic), $ephemeral, 32);
        if ($ecdhSecret === false) {
            throw new RuntimeException('ECDH не удался');
        }
        $salt ??= random_bytes(16);

        $ikm = hash_hkdf('sha256', $ecdhSecret, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // Одна запись: данные + разделитель 0x02 (последняя запись)
        $tag = '';
        $cipher = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** VAPID JWT (ES256) для origin push-сервиса */
    public function vapidJwt(string $audience): string
    {
        $header = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64(json_encode(['aud' => $audience, 'exp' => now()->addHours(12)->getTimestamp(), 'sub' => config('shop.push.subject')]));
        $data = "{$header}.{$claims}";
        openssl_sign($data, $der, $this->vapidPrivatePem(), OPENSSL_ALGO_SHA256);

        return $data.'.'.self::b64(self::derToRaw($der));
    }

    /** Приватный ключ VAPID в PEM из сырого d и публичной точки (SEC1 ECPrivateKey) */
    private function vapidPrivatePem(): string
    {
        $d = self::unb64((string) config('shop.push.private_key'));
        $pub = self::unb64((string) config('shop.push.public_key'));
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$pub;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    public static function publicKeyFromPoint(string $point): \OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX).$point), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Некорректный публичный ключ');
        }

        return $key;
    }

    /** @param array{x: string, y: string} $ec */
    public static function publicPoint(array $ec): string
    {
        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** ECDSA-подпись DER (SEQUENCE{INTEGER r, INTEGER s}) → 64 байта r||s, как требует JWS ES256 */
    public static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $len);
            $parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $parts[0].$parts[1];
    }

    public static function origin(string $endpoint): string
    {
        $u = parse_url($endpoint);

        return $u['scheme'].'://'.$u['host'].(isset($u['port']) ? ':'.$u['port'] : '');
    }

    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
