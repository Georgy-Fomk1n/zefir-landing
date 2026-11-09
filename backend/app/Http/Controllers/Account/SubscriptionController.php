<?php

namespace App\Http\Controllers\Account;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Loyalty\QrToken;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service) {}

    public static function plan(SubscriptionPlan $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $p->price,
            'periodDays' => $p->period_days, 'dailyLimit' => $p->daily_limit, 'totalLimit' => $p->total_limit];
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->where('is_active', true)->orderBy('price')->get()->map(self::plan(...))]);
    }

    public function index(Request $request, QrToken $qr): JsonResponse
    {
        $subs = $request->user()->subscriptions()->with('plan')->latest()->get();

        return response()->json(['data' => $subs->map(fn (Subscription $s) => [
            'id' => $s->id,
            'plan' => self::plan($s->plan),
            'status' => $s->status->value,
            'currentPeriodEnd' => $s->current_period_end?->toIso8601String(),
            'autoRenew' => $s->auto_renew,
            'usedToday' => $this->service->usedToday($s),
            'qrToken' => $s->status->isUsable() ? $qr->issue('subscription', $s->id)['token'] : null,
            'renewalMode' => $s->payment_method_id ? 'recurring' : 'link',
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($request->validate(['planId' => ['required', 'integer']])['planId']);
        $result = $this->service->purchase($request->user(), $plan);

        return response()->json(['data' => ['id' => $result['subscription']->id], 'payment' => ['url' => $result['url']]], 201);
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        if (in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Pending], true)) {
            abort(422, 'Абонемент не активен');
        }
        $this->service->cancel($subscription);

        return response()->json(null, 204);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->paymentMethods()->get(['id', 'title'])]);
    }

    public function unlinkCard(Request $request, int $id): JsonResponse
    {
        $method = $request->user()->paymentMethods()->findOrFail($id);
        Subscription::query()->where('payment_method_id', $method->id)->update(['payment_method_id' => null]);
        $method->delete();

        return response()->json(null, 204);
    }
}
