<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Провайдер для разработки и тестов. Ссылка ведёт на /api/payments/fake/{id} (подписанный URL),
 * где можно «оплатить» или «отклонить». Вебхук подписывается HMAC-SHA256 от APP_KEY.
 * На production не используется: PaymentServiceProvider падает при PAYMENT_PROVIDER=fake и APP_ENV=production.
 */
class FakePaymentProvider implements PaymentProvider
{
    public function code(): string
    {
        return 'fake';
    }

    public function create(Payment $payment, string $description, string $successUrl, string $failUrl, bool $saveCard, array $items = []): PaymentInit
    {
        $id = 'fake_'.$payment->id.'_'.bin2hex(random_bytes(4));
        $url = URL::temporarySignedRoute('payments.fake', now()->addHour(), [
            'payment' => $payment->id, 'success' => $successUrl, 'fail' => $failUrl, 'save' => (int) $saveCard,
        ]);

        return new PaymentInit($id, $url);
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('app.key'));
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $body = $request->getContent();
        if (! hash_equals(self::sign($body), (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidWebhook('bad signature');
        }
        $data = json_decode($body, true) ?? [];

        return new WebhookResult(
            (string) ($data['eventId'] ?? ''),
            (string) ($data['paymentId'] ?? ''),
            PaymentStatus::from($data['status'] ?? 'failed'),
            $data['cardToken'] ?? null,
            $data['cardTitle'] ?? null,
            $data,
        );
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        return $payment->status;
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function chargeRecurring(Payment $payment, string $savedToken, string $description): PaymentStatus
    {
        // Токен «fake_decline» имитирует отказ банка
        return $savedToken === 'fake_decline' ? PaymentStatus::Failed : PaymentStatus::Paid;
    }

    public function refund(Payment $payment, int $amount): bool
    {
        return true;
    }
}
