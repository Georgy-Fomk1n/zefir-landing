<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function code(): string;

    /**
     * Создать платёж и получить ссылку на оплату.
     *
     * @param  list<array{name: string, price: int, qty: int}>  $items  позиции для чека (54-ФЗ), если фискализация через провайдера
     */
    public function create(Payment $payment, string $description, string $successUrl, string $failUrl, bool $saveCard, array $items = []): PaymentInit;

    /** Разобрать и проверить подпись вебхука. Неверная подпись → InvalidWebhook. */
    public function parseWebhook(Request $request): WebhookResult;

    /** Сверка статуса по API (планировщик payments:reconcile) */
    public function fetchStatus(Payment $payment): PaymentStatus;

    /** Поддерживает ли провайдер списание с сохранённой карты без участия покупателя */
    public function supportsRecurring(): bool;

    /** Рекуррентное списание. Возвращает итоговый или промежуточный статус. */
    public function chargeRecurring(Payment $payment, string $savedToken, string $description): PaymentStatus;

    public function refund(Payment $payment, int $amount): bool;
}
