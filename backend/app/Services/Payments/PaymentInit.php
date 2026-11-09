<?php

namespace App\Services\Payments;

final class PaymentInit
{
    public function __construct(public string $providerPaymentId, public string $url) {}
}
