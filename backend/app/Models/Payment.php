<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = ['payable_type', 'payable_id', 'provider', 'provider_payment_id', 'idempotency_key', 'amount', 'status', 'purpose', 'payment_url', 'payload', 'paid_at'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'payload' => 'array', 'paid_at' => 'datetime', 'amount' => 'integer'];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
