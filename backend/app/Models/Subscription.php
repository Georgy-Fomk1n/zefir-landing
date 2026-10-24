<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'subscription_plan_id', 'payment_method_id', 'status', 'auto_renew', 'current_period_start', 'current_period_end',
        'retry_count', 'next_retry_at', 'grace_until', 'reminded_at', 'cancelled_at', 'provider_subscription_id'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'auto_renew' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_retry_at' => 'datetime',
            'grace_until' => 'datetime',
            'reminded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(SubscriptionRedemption::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
