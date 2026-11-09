<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Интернет-эквайринг Точки (https://developers.tochka.com/docs/tochka-api/).
 * Используемые методы (база https://enter.tochka.com/uapi, авторизация — JWT-ключ из интернет-банка):
 *   POST /acquiring/v1.0/payments                     — платёжная ссылка (без чека)
 *   POST /acquiring/v1.0/payments_with_receipt        — платёжная ссылка с чеком 54-ФЗ
 *   GET  /acquiring/v1.0/payments/{operationId}       — статус операции
 *   POST /acquiring/v1.0/payments/{operationId}/refund — возврат (только для APPROVED)
 *   POST /acquiring/v1.0/subscriptions, .../charge    — подписки (только карта)
 * Вебхук acquiringInternetPayment приходит как JWT, подписанный ключом Точки (RS256).
 *
 * ВНИМАНИЕ: имена полей тел запросов/ответов ниже нужно сверить с актуальной OpenAPI-спецификацией
 * перед включением PAYMENT_PROVIDER=tochka (docs/payments.md, чек-лист). Рекуррентные списания
 * включаются флагом TOCHKA_RECURRING только после проверки договора; иначе — продление по ссылке.
 */
class TochkaPaymentProvider implements PaymentProvider
{
    public function code(): string
    {
        return 'tochka';
    }

    private function http(): PendingRequest
    {
        $cfg = config('shop.payments.tochka');
        if (! $cfg['token'] || ! $cfg['customer_code']) {
            throw new RuntimeException('Tochka: не заданы TOCHKA_JWT / TOCHKA_CUSTOMER_CODE');
        }

        return Http::baseUrl(rtrim($cfg['base_url'], '/'))->withToken($cfg['token'])->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }

    public function create(Payment $payment, string $description, string $successUrl, string $failUrl, bool $saveCard, array $items = []): PaymentInit
    {
        $cfg = config('shop.payments.tochka');
        $data = array_filter([
            'customerCode' => $cfg['customer_code'],
            'merchantId' => $cfg['merchant_id'],
            'amount' => number_format($payment->amount / 100, 2, '.', ''),
            'purpose' => mb_substr($description, 0, 140),
            'redirectUrl' => $successUrl,
            'failRedirectUrl' => $failUrl,
            'paymentMode' => $saveCard ? ['card'] : ['card', 'sbp'],
            'saveCard' => $saveCard ?: null,
            // Уникальный идентификатор ссылки (обязателен с 27.03.2026 по changelog Точки)
            'paymentLinkId' => $payment->idempotency_key,
        ], fn ($v) => $v !== null);

        $withReceipt = config('shop.payments.fiscalization') === 'tochka_receipt' && $items !== [];
        if ($withReceipt) {
            $data['taxSystemCode'] = config('shop.payments.tax_system');
            $data['Items'] = array_map(fn ($i) => [
                'name' => mb_substr($i['name'], 0, 128), 'amount' => number_format($i['price'] / 100, 2, '.', ''),
                'quantity' => $i['qty'], 'vatType' => 'none', 'paymentMethod' => 'full_payment', 'paymentObject' => 'goods',
            ], $items);
        }

        $res = $this->http()->post($withReceipt ? '/acquiring/v1.0/payments_with_receipt' : '/acquiring/v1.0/payments', ['Data' => $data]);
        if (! $res->successful() || ! $res->json('Data.operationId')) {
            throw new RuntimeException('Tochka: не удалось создать платёж, HTTP '.$res->status());
        }

        return new PaymentInit($res->json('Data.operationId'), $res->json('Data.paymentLink'));
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $payload = $this->verifyJwt(trim($request->getContent()));
        if (($payload['webhookType'] ?? null) !== 'acquiringInternetPayment') {
            throw new InvalidWebhook('unsupported webhook type');
        }
        $status = match ($payload['status'] ?? null) {
            'APPROVED', 'AUTHORIZED' => PaymentStatus::Paid,
            'REFUNDED' => PaymentStatus::Refunded,
            'EXPIRED', 'REFUNDED_PARTIALLY', 'ON-REFUND' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
        $operationId = (string) ($payload['operationId'] ?? '');

        return new WebhookResult(
            $operationId.':'.($payload['status'] ?? ''),
            $operationId,
            $status,
            $payload['cardToken'] ?? null,
            isset($payload['maskedPan']) ? 'Карта '.$payload['maskedPan'] : null,
            $payload,
        );
    }

    /** Проверка JWT RS256 публичным ключом Точки */
    private function verifyJwt(string $jwt): array
    {
        $key = config('shop.payments.tochka.webhook_public_key');
        $parts = explode('.', $jwt);
        if (! $key || count($parts) !== 3) {
            throw new InvalidWebhook('malformed jwt or no key');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::unb64($h), true);
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new InvalidWebhook('unexpected alg');
        }
        $pem = str_contains($key, 'BEGIN') ? $key : "-----BEGIN PUBLIC KEY-----\n".chunk_split($key, 64, "\n").'-----END PUBLIC KEY-----';
        if (openssl_verify("{$h}.{$p}", self::unb64($s), $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidWebhook('bad signature');
        }

        return json_decode(self::unb64($p), true) ?? [];
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        $res = $this->http()->get('/acquiring/v1.0/payments/'.rawurlencode((string) $payment->provider_payment_id));
        $status = $res->json('Data.Operation.0.status') ?? $res->json('Data.status');

        return match ($status) {
            'APPROVED', 'AUTHORIZED' => PaymentStatus::Paid,
            'REFUNDED' => PaymentStatus::Refunded,
            'EXPIRED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    public function supportsRecurring(): bool
    {
        return (bool) config('shop.payments.tochka.recurring');
    }

    public function chargeRecurring(Payment $payment, string $savedToken, string $description): PaymentStatus
    {
        if (! $this->supportsRecurring()) {
            throw new RuntimeException('Рекуррентные списания Точки не включены (TOCHKA_RECURRING)');
        }
        $res = $this->http()->post('/acquiring/v1.0/subscriptions/'.rawurlencode($savedToken).'/charge', ['Data' => [
            'customerCode' => config('shop.payments.tochka.customer_code'),
            'amount' => number_format($payment->amount / 100, 2, '.', ''),
            'purpose' => mb_substr($description, 0, 140),
        ]]);
        if (! $res->successful()) {
            return PaymentStatus::Failed;
        }
        $payment->provider_payment_id = $res->json('Data.operationId') ?? $payment->provider_payment_id;

        return PaymentStatus::Pending; // итог придёт вебхуком
    }

    public function refund(Payment $payment, int $amount): bool
    {
        $res = $this->http()->post('/acquiring/v1.0/payments/'.rawurlencode((string) $payment->provider_payment_id).'/refund', ['Data' => [
            'amount' => number_format($amount / 100, 2, '.', ''),
        ]]);

        return $res->successful();
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
