<?php

namespace App\Services\Subscriptions;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRedemption;
use App\Models\User;
use App\Notifications\SubscriptionRenewalLink;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Абонементы. Предмет абонемента (десерты/кофе) — [УТОЧНИТЬ], тарифы задаются в админке.
 * Два режима продления:
 *  • recurring — провайдер умеет списывать с сохранённой карты: списание за renew_days_before дней,
 *    при отказе — повторы через retry_hours, затем льготный период grace_days, затем expired;
 *  • link — рекуррентов нет (Точка без включённых подписок): за reminder_days_before дней отправляется
 *    ссылка на оплату, после окончания периода — льготный период, затем expired.
 */
class SubscriptionService
{
    public function __construct(private PaymentProvider $provider) {}

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    public function mode(): string
    {
        return $this->provider->supportsRecurring() ? 'recurring' : 'link';
    }

    /** @return array{subscription: Subscription, url: string} */
    public function purchase(User $user, SubscriptionPlan $plan): array
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['planId' => 'Тариф недоступен']);
        }
        $active = $user->subscriptions()->where('subscription_plan_id', $plan->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Grace])->exists();
        if ($active) {
            throw ValidationException::withMessages(['planId' => 'Абонемент этого тарифа уже оформлен']);
        }
        $sub = Subscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Pending,
            'auto_renew' => true,
        ]);
        $back = rtrim(config('shop.public_url'), '/').'/account/subscriptions';
        $payment = $this->payments()->start($sub, $plan->price, "Абонемент «{$plan->name}»", $back.'?paid=1', $back,
            saveCard: $this->provider->supportsRecurring(), items: [['name' => "Абонемент «{$plan->name}»", 'price' => $plan->price, 'qty' => 1]]);

        return ['subscription' => $sub, 'url' => (string) $payment->payment_url];
    }

    public function paymentSucceeded(Subscription $sub, Payment $payment, ?string $cardToken, ?string $cardTitle): void
    {
        DB::transaction(function () use ($sub, $cardToken, $cardTitle) {
            $sub->refresh();
            $days = $sub->plan->period_days;
            if ($sub->status === SubscriptionStatus::Pending) {
                $sub->status = SubscriptionStatus::Active;
                $sub->current_period_start = now();
                $sub->current_period_end = now()->addDays($days);
            } else {
                $this->transition($sub, SubscriptionStatus::Active);
                // Продление от конца текущего периода; если период уже истёк (льготный) — от текущего момента
                $start = $sub->current_period_end && $sub->current_period_end->isFuture() ? $sub->current_period_end : now();
                $sub->current_period_start = $start;
                $sub->current_period_end = $start->copy()->addDays($days);
            }
            $sub->retry_count = 0;
            $sub->next_retry_at = null;
            $sub->grace_until = null;
            $sub->reminded_at = null;
            if ($cardToken && $this->provider->supportsRecurring()) {
                $method = PaymentMethod::create(['user_id' => $sub->user_id, 'provider' => $this->provider->code(), 'token' => $cardToken, 'title' => $cardTitle ?? 'Сохранённая карта']);
                $sub->payment_method_id = $method->id;
            }
            $sub->save();
        });
    }

    public function paymentFailed(Subscription $sub, Payment $payment): void
    {
        $sub->refresh();
        if ($payment->purpose !== 'renewal' || $sub->status === SubscriptionStatus::Pending) {
            return;
        }
        $this->registerRenewalFailure($sub);
    }

    public function registerRenewalFailure(Subscription $sub): void
    {
        $retries = config('shop.subscriptions.retry_hours');
        $sub->retry_count++;
        if ($sub->retry_count <= count($retries)) {
            $this->transition($sub, SubscriptionStatus::PastDue);
            $sub->next_retry_at = now()->addHours($retries[$sub->retry_count - 1]);
        } else {
            $this->transition($sub, SubscriptionStatus::Grace);
            $sub->next_retry_at = null;
            $sub->grace_until = ($sub->current_period_end && $sub->current_period_end->isFuture() ? $sub->current_period_end : now())
                ->copy()->addDays(config('shop.subscriptions.grace_days'));
        }
        $sub->save();
    }

    /** Отмена автопродления в кабинете: оплаченный период остаётся. */
    public function cancel(Subscription $sub): void
    {
        $sub->auto_renew = false;
        $sub->cancelled_at = now();
        if ($sub->status !== SubscriptionStatus::Cancelled) {
            $this->transition($sub, SubscriptionStatus::Cancelled);
        }
        $sub->next_retry_at = null;
        $sub->save();
    }

    /** Планировщик: продления, повторы, напоминания, льготный период, истечение. */
    public function process(): array
    {
        $stats = ['charged' => 0, 'reminded' => 0, 'grace' => 0, 'expired' => 0];
        $renewBefore = now()->addDays(config('shop.subscriptions.renew_days_before'));

        // 1. Истечение: отменённые и льготные с прошедшим сроком
        Subscription::query()->where('status', SubscriptionStatus::Cancelled)->where('current_period_end', '<', now())->get()
            ->each(function (Subscription $s) use (&$stats) {
                $this->transition($s, SubscriptionStatus::Expired);
                $s->save();
                $stats['expired']++;
            });
        Subscription::query()->where('status', SubscriptionStatus::Grace)->where('grace_until', '<', now())->get()
            ->each(function (Subscription $s) use (&$stats) {
                $this->transition($s, SubscriptionStatus::Expired);
                $s->save();
                $stats['expired']++;
            });

        if ($this->mode() === 'recurring') {
            // 2. Плановое списание за N дней до конца периода
            Subscription::query()->where('status', SubscriptionStatus::Active)->where('auto_renew', true)
                ->whereNotNull('payment_method_id')->where('current_period_end', '<=', $renewBefore)
                ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))->get()
                ->each(function (Subscription $s) use (&$stats) {
                    $this->charge($s);
                    $stats['charged']++;
                });
            // 3. Повторы после отказа
            Subscription::query()->where('status', SubscriptionStatus::PastDue)->where('next_retry_at', '<=', now())->get()
                ->each(function (Subscription $s) use (&$stats) {
                    $this->charge($s);
                    $stats['charged']++;
                });
            // Активные без сохранённой карты продлеваются ссылкой, как в режиме link
        }

        // 4. Режим ссылки: напоминание со ссылкой на оплату; после конца периода — льготный период
        Subscription::query()->where('status', SubscriptionStatus::Active)->where('auto_renew', true)
            ->when($this->mode() === 'recurring', fn ($q) => $q->whereNull('payment_method_id'))
            ->whereNull('reminded_at')->where('current_period_end', '<=', now()->addDays(config('shop.subscriptions.reminder_days_before')))->get()
            ->each(function (Subscription $s) use (&$stats) {
                $this->sendRenewalLink($s);
                $stats['reminded']++;
            });
        Subscription::query()->where('status', SubscriptionStatus::Active)->where('current_period_end', '<', now())
            ->when($this->mode() === 'recurring', fn ($q) => $q->whereNull('payment_method_id'))->get()
            ->each(function (Subscription $s) use (&$stats) {
                $this->transition($s, SubscriptionStatus::Grace);
                $s->grace_until = now()->addDays(config('shop.subscriptions.grace_days'));
                $s->save();
                $stats['grace']++;
            });

        return $stats;
    }

    private function charge(Subscription $sub): void
    {
        $method = $sub->paymentMethod;
        if (! $method) {
            $this->registerRenewalFailure($sub);

            return;
        }
        $payment = Payment::create([
            'payable_type' => $sub->getMorphClass(), 'payable_id' => $sub->id, 'provider' => $this->provider->code(),
            'idempotency_key' => (string) Str::uuid(), 'amount' => $sub->plan->price, 'status' => PaymentStatus::Pending, 'purpose' => 'renewal',
        ]);
        $status = $this->provider->chargeRecurring($payment, $method->token, "Продление абонемента «{$sub->plan->name}»");
        $payment->save();
        if ($status !== PaymentStatus::Pending) {
            $this->payments()->apply($payment, $status);
        } else {
            // Ждём вебхук; чтобы не списать повторно, откладываем следующую попытку
            $sub->forceFill(['next_retry_at' => now()->addHours(config('shop.subscriptions.retry_hours')[0])])->save();
        }
    }

    private function sendRenewalLink(Subscription $sub): void
    {
        $back = rtrim(config('shop.public_url'), '/').'/account/subscriptions';
        $payment = $this->payments()->start($sub, $sub->plan->price, "Продление абонемента «{$sub->plan->name}»", $back.'?paid=1', $back,
            items: [['name' => "Абонемент «{$sub->plan->name}»", 'price' => $sub->plan->price, 'qty' => 1]], purpose: 'renewal');
        $sub->user->notify(new SubscriptionRenewalLink($sub, (string) $payment->payment_url));
        $sub->forceFill(['reminded_at' => now()])->save();
    }

    /** Погашение по QR продавцом: лимит в день и общий лимит тарифа. */
    public function redeem(Subscription $sub, User $seller): array
    {
        return DB::transaction(function () use ($sub, $seller) {
            /** @var Subscription $s */
            $s = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($sub->id);
            $periodOk = $s->current_period_end && ($s->current_period_end->isFuture() || ($s->status === SubscriptionStatus::Grace && $s->grace_until?->isFuture()));
            if (! $s->status->isUsable() || ! $periodOk) {
                throw ValidationException::withMessages(['token' => 'Абонемент не активен']);
            }
            $today = now(config('shop.timezone'))->toDateString();
            $usedToday = $s->redemptions()->whereDate('redeemed_on', $today)->count();
            if ($usedToday >= $s->plan->daily_limit) {
                throw ValidationException::withMessages(['token' => 'Дневной лимит абонемента исчерпан']);
            }
            if ($s->plan->total_limit !== null && $s->redemptions()->where('created_at', '>=', $s->current_period_start)->count() >= $s->plan->total_limit) {
                throw ValidationException::withMessages(['token' => 'Лимит абонемента на период исчерпан']);
            }
            SubscriptionRedemption::create(['subscription_id' => $s->id, 'seller_id' => $seller->id, 'redeemed_on' => $today]);

            return ['plan' => $s->plan->name, 'usedToday' => $usedToday + 1, 'dailyLimit' => $s->plan->daily_limit];
        });
    }

    public function usedToday(Subscription $sub): int
    {
        return $sub->redemptions()->whereDate('redeemed_on', now(config('shop.timezone'))->toDateString())->count();
    }

    private function transition(Subscription $sub, SubscriptionStatus $to): void
    {
        $sub->status->assertTransitionTo($to);
        $sub->status = $to;
    }
}
