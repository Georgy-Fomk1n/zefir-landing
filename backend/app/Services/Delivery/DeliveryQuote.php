<?php

namespace App\Services\Delivery;

final class DeliveryQuote
{
    public function __construct(
        public string $provider,
        public bool $available,
        public int $cost = 0,
        public string $etaText = '',
        public ?int $zoneId = null,
        public ?string $message = null,
    ) {}

    public static function unavailable(string $provider, string $message): self
    {
        return new self($provider, false, 0, '', null, $message);
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'available' => $this->available,
            'cost' => $this->cost,
            'etaText' => $this->etaText,
            'zoneId' => $this->zoneId,
            'message' => $this->message,
        ];
    }
}
