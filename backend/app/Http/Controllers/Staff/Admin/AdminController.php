<?php

namespace App\Http\Controllers\Staff\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\CatalogChanged;
use App\Http\Controllers\Controller;
use App\Http\Presenters\UserPresenter;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Review;
use App\Models\SeoPage;
use App\Models\SubscriptionPlan;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Orders\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    // ── Промокоды ────────────────────────────────────────────────
    public function promoCodes(): JsonResponse
    {
        return response()->json(['data' => PromoCode::query()->latest()->get()]);
    }

    public function savePromoCode(Request $request, ?PromoCode $promoCode = null): JsonResponse
    {
        $promoCode ??= new PromoCode;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('promo_codes', 'code')->ignore($promoCode->id)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:10000000'],
            'min_total' => ['integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ]);
        $promoCode->fill($data)->save();

        return response()->json(['data' => $promoCode]);
    }

    // ── Сотрудники и роли (саморегистрации нет) ──────────────────
    public function users(Request $request): JsonResponse
    {
        $q = User::query()->whereNull('anonymized_at')
            ->when($request->query('staff'), fn ($w) => $w->whereIn('id', DB::table('user_roles')->whereIn('role', ['seller', 'courier', 'admin'])->pluck('user_id')))
            ->when($request->query('q'), fn ($w, $s) => $w->where(fn ($x) => $x->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")))
            ->orderBy('name')->limit(200)->get();

        return response()->json(['data' => $q->map(fn (User $u) => UserPresenter::make($u) + ['isActive' => $u->is_active])]);
    }

    public function saveUser(Request $request, ?User $user = null): JsonResponse
    {
        $creating = $user === null;
        $user ??= new User;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$creating ? 'required' : 'nullable', Password::min(10)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
            'is_active' => ['boolean'],
        ]);
        if (! $creating && $user->id === $request->user()->id && ! in_array('admin', $data['roles'], true)) {
            abort(422, 'Нельзя снять с себя роль администратора');
        }
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'is_active'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles(array_map(fn ($r) => Role::from($r), $data['roles']));
        if (($data['is_active'] ?? true) === false || ! $creating) {
            // Смена ролей или блокировка — принудительный повторный вход
            $user->tokens()->where('name', 'staff')->delete();
        }

        return response()->json(['data' => UserPresenter::make($user)], $creating ? 201 : 200);
    }

    public function resetTwoFactor(User $user): JsonResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $user->tokens()->where('name', 'staff')->delete();

        return response()->json(null, 204);
    }

    // ── Зоны и тарифы доставки ───────────────────────────────────
    public function zones(): JsonResponse
    {
        return response()->json(['data' => DeliveryZone::query()->orderBy('sort')->get()]);
    }

    public function saveZone(Request $request, ?DeliveryZone $zone = null): JsonResponse
    {
        $zone ??= new DeliveryZone;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'polygon' => ['required', 'array', 'min:3'],
            'polygon.*' => ['array', 'size:2'],
            'polygon.*.*' => ['numeric'],
            'price' => ['required', 'integer', 'min:0'],
            'min_order' => ['integer', 'min:0'],
            'free_from' => ['nullable', 'integer', 'min:0'],
            'eta_minutes' => ['integer', 'min:15'],
            'max_transit_minutes' => ['nullable', 'integer', 'min:10'],
            'sort' => ['integer'],
            'is_active' => ['boolean'],
        ]);
        $zone->fill($data)->save();
        Cache::forget('catalog:export');
        CatalogChanged::dispatch(['/dostavka-i-oplata/']);

        return response()->json(['data' => $zone]);
    }

    // ── Слоты ────────────────────────────────────────────────────
    public function slots(Request $request): JsonResponse
    {
        $date = $request->query('date', now(config('shop.timezone'))->toDateString());

        return response()->json(['data' => TimeSlot::query()->whereDate('date', $date)->orderBy('type')->orderBy('starts_at')->get()]);
    }

    public function updateSlot(Request $request, TimeSlot $slot): JsonResponse
    {
        $data = $request->validate(['capacity' => ['sometimes', 'integer', 'min:0', 'max:100'], 'is_active' => ['sometimes', 'boolean']]);
        $slot->update($data);

        return response()->json(['data' => $slot]);
    }

    public function generateSlots(SlotService $slots): JsonResponse
    {
        return response()->json(['created' => $slots->generate()]);
    }

    // ── Модерация отзывов ────────────────────────────────────────
    public function reviews(Request $request): JsonResponse
    {
        return response()->json(['data' => Review::query()->with('product:id,name,slug')
            ->where('status', $request->query('status', 'pending'))->latest()->limit(200)->get()]);
    }

    public function moderateReview(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $review->update($data);
        $product = $review->product()->with('category')->first();
        Cache::forget('catalog:export');
        CatalogChanged::dispatch(["/catalog/{$product->category->slug}/{$product->slug}/", '/otzyvy/']);

        return response()->json(['data' => $review]);
    }

    // ── Тарифы абонементов ───────────────────────────────────────
    public function plans(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->orderBy('price')->get()]);
    }

    public function savePlan(Request $request, ?SubscriptionPlan $plan = null): JsonResponse
    {
        $plan ??= new SubscriptionPlan;
        $plan->fill($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:100'],
            'period_days' => ['required', 'integer', 'min:1', 'max:366'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'total_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]))->save();

        return response()->json(['data' => $plan]);
    }

    // ── SEO-поля контентных страниц ──────────────────────────────
    public function seoPages(): JsonResponse
    {
        return response()->json(['data' => SeoPage::query()->orderBy('path')->get()]);
    }

    public function saveSeoPage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'regex:#^/([a-z0-9-]+/)*$#'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_h1' => ['nullable', 'string', 'max:190'],
            'seo_text' => ['nullable', 'string', 'max:10000'],
            'noindex' => ['boolean'],
        ]);
        $page = SeoPage::query()->updateOrCreate(['path' => $data['path']], $data);
        Cache::forget('catalog:export');
        CatalogChanged::dispatch([$data['path']]);

        return response()->json(['data' => $page]);
    }

    // ── Отчёты ───────────────────────────────────────────────────
    public function report(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $orders = Order::query()->whereBetween('created_at', [$from, $to])->where('status', '!=', OrderStatus::Cancelled->value);
        $deliveries = Delivery::query()->whereBetween('created_at', [$from, $to]);
        $done = (clone $deliveries)->where('status', DeliveryStatus::Delivered->value)->get();
        $avgMinutes = $done->map(function (Delivery $d) {
            $picked = $d->history()->where('to', DeliveryStatus::PickedUp->value)->value('happened_at');

            return $picked && $d->delivered_at ? $d->delivered_at->diffInMinutes($picked, true) : null;
        })->filter()->avg();

        return response()->json(['data' => [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'orders' => (clone $orders)->count(),
            'revenue' => (int) (clone $orders)->where('payment_status', 'paid')->sum('total'),
            'avgCheck' => (int) (clone $orders)->where('payment_status', 'paid')->avg('total'),
            'byFulfilment' => (clone $orders)->select('fulfilment', DB::raw('count(*) as n'))->groupBy('fulfilment')->pluck('n', 'fulfilment'),
            'deliveries' => [
                'total' => (clone $deliveries)->count(),
                'delivered' => $done->count(),
                'failedShare' => ($t = (clone $deliveries)->count()) ? round((clone $deliveries)->whereIn('status', ['failed', 'returned'])->count() / $t, 3) : 0,
                'avgDeliveryMinutes' => $avgMinutes ? (int) round($avgMinutes) : null,
                'byCourier' => (clone $deliveries)->whereNotNull('courier_id')->select('courier_id', DB::raw('count(*) as n'))
                    ->groupBy('courier_id')->get()->map(fn ($r) => ['courierId' => $r->courier_id, 'name' => User::query()->find($r->courier_id)?->name, 'deliveries' => $r->n]),
            ],
            // [УТОЧНИТЬ] модель оплаты курьеров: пока — количество доставок за период для расчёта вручную
            'courierPayoutModel' => 'manual',
        ]]);
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Номер', 'Дата', 'Статус', 'Получение', 'Сумма, ₽', 'Оплата', 'Курьер', 'Статус доставки'], ';');
            Order::query()->with('delivery.courier')->whereBetween('created_at', [$from, $to])->orderBy('id')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $o) {
                        fputcsv($out, [$o->number, $o->created_at->setTimezone(config('shop.timezone'))->format('d.m.Y H:i'), $o->status->label(), $o->fulfilment->value,
                            number_format($o->total / 100, 2, ',', ''), $o->payment_status->value, $o->delivery?->courier?->name, $o->delivery?->status->label()], ';');
                    }
                });
            fclose($out);
        }, 'orders-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function period(Request $request): array
    {
        $tz = config('shop.timezone');
        $from = $request->date('from', 'Y-m-d', $tz)?->startOfDay() ?? now($tz)->startOfMonth();
        $to = $request->date('to', 'Y-m-d', $tz)?->endOfDay() ?? now($tz)->endOfDay();

        return [$from->utc(), $to->utc()];
    }

    public function newPassword(): JsonResponse
    {
        return response()->json(['password' => Str::password(14)]);
    }
}
