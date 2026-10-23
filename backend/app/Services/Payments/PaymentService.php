<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Orders\OrderLifecycle;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Платежи: создание, вебхуки (подпись + идемпотентность), сверка по расписанию.
 * Итог платежа применяется к объекту оплаты (заказ или абонемент) ровно один раз.
 */
class PaymentService
{
    public function __construct(
        private PaymentProvider $provider,
        private OrderLifecycle $orders,
        private SubscriptionService $subscriptions,
    ) {}

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    /** @param list<array{name: string, price: int, qty: int}> $items */
    public function start(Model $payable, int $amount, string $description, string $successUrl, string $failUrl, bool $saveCard = false, array $items = [], string $purpose = 'initial'): Payment
    {
        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => $this->provider->code(),
            'idempotency_key' => (string) Str::uuid(),
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'purpose' => $purpose,
        ]);
        $init = $this->provider->create($payment, $description, $successUrl, $failUrl, $saveCard, $items);
        $payment->update(['provider_payment_id' => $init->providerPaymentId, 'payment_url' => $init->url]);

        return $payment;
    }

    /** Обработка вебхука. Повтор того же события — no-op (уникальный индекс provider+event_id). */
    public function handleWebhook(Request $request): void
    {
        $result = $this->provider->parseWebhook($request);
        try {
            $event = WebhookEvent::create(['provider' => $this->provider->code(), 'event_id' => $result->eventId, 'payload' => $result->raw]);
        } catch (UniqueConstraintViolationException) {
            return;
        }
        $payment = Payment::query()->where('provider', $this->provider->code())->where('provider_payment_id', $result->providerPaymentId)->first();
        if (! $payment) {
            Log::warning('webhook for unknown payment', ['id' => $result->providerPaymentId]);
            $event->update(['processed_at' => now()]);

            return;
        }
        $this->apply($payment, $result->status, $result->cardToken, $result->cardTitle);
        $event->update(['processed_at' => now()]);
    }

    /** Применить статус к платежу и объекту оплаты. Идемпотентно: финальный статус не перезаписывается. */
    public function apply(Payment $payment, PaymentStatus $status, ?string $cardToken = null, ?string $cardTitle = null): void
    {
        if ($status === PaymentStatus::Pending) {
            return;
        }
        $changed = DB::transaction(function () use ($payment, $status) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status->isFinal() && ! ($locked->status === PaymentStatus::Paid && $status === PaymentStatus::Refunded)) {
                return false;
            }
            $locked->status = $status;
            $locked->paid_at = $status === PaymentStatus::Paid ? now() : $locked->paid_at;
            $locked->save();
            $payment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
        if (! $changed) {
            return;
        }
        $payable = $payment->payable;
        if ($payable instanceof Order) {
            match ($status) {
                PaymentStatus::Paid => $this->orders->markPaid($payable),
                PaymentStatus::Failed => $this->orders->paymentFailed($payable),
                PaymentStatus::Refunded => $this->orders->markRefunded($payable),
                default => null,
            };
        } elseif ($payable instanceof Subscription) {
            match ($status) {
                PaymentStatus::Paid => $this->subscriptions->paymentSucceeded($payable, $payment, $cardToken, $cardTitle),
                PaymentStatus::Failed => $this->subscriptions->paymentFailed($payable, $payment),
                default => null,
            };
        }
    }

    /** Сверка зависших платежей (планировщик, каждые 10 минут): страхует от потерянных вебхуков. */
    public function reconcile(): int
    {
        $n = 0;
        Payment::query()->where('status', PaymentStatus::Pending)->whereNotNull('provider_payment_id')
            ->where('created_at', '<', now()->subMinutes(5))->where('created_at', '>', now()->subDays(2))
            ->orderBy('id')->limit(100)->get()
            ->each(function (Payment $p) use (&$n) {
                $status = $this->provider->fetchStatus($p);
                if ($status !== PaymentStatus::Pending) {
                    $this->apply($p, $status);
                    $n++;
                }
            });

        return $n;
    }
}
