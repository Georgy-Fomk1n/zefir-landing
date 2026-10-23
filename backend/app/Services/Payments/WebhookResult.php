<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;

final class WebhookResult
{
    public function __construct(
        /** Уникальный id события для идемпотентности */
        public string $eventId,
        public string $providerPaymentId,
        public PaymentStatus $status,
        public ?string $cardToken = null,
        public ?string $cardTitle = null,
        public array $raw = [],
    ) {}
}
