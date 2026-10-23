<?php

namespace App\Http\Controllers;

use App\Enums\Fulfilment;
use App\Http\Requests\CartLinesRules;
use App\Http\Requests\CartQuoteRequest;
use App\Models\ProductVariant;
use App\Services\Orders\CartPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    use CartLinesRules;

    public function quote(CartQuoteRequest $request, CartPricing $pricing): JsonResponse
    {
        $user = $request->user('sanctum');
        $user = $user?->tokenCan('customer') ? $user : null;
        $q = $pricing->quote($request->input('lines', []), Fulfilment::from($request->input('fulfilment')), $request->input('address'),
            $request->input('promoCode'), (int) $request->input('points', 0), $user);

        return response()->json(['data' => [
            'subtotal' => $q['subtotal'],
            'discount' => $q['discount'],
            'promoError' => $q['promoError'],
            'pointsSpent' => $q['pointsSpent'] * 100,
            'maxPoints' => $q['maxPoints'],
            'delivery' => $q['delivery']?->toArray(),
            'deliveryCost' => $q['deliveryCost'],
            'total' => $q['total'],
        ]]);
    }

    /** Слияние гостевой корзины с серверной при входе. Количество — максимум из двух. */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate($this->lineRules());
        $user = $request->user();
        $valid = ProductVariant::query()->whereIn('id', collect($data['lines'])->pluck('variantId'))->where('is_active', true)->pluck('id')->all();
        DB::transaction(function () use ($data, $user, $valid) {
            foreach ($data['lines'] as $l) {
                if (! in_array($l['variantId'], $valid, true)) {
                    continue;
                }
                $current = (int) DB::table('cart_items')->where(['user_id' => $user->id, 'product_variant_id' => $l['variantId']])->value('qty');
                DB::table('cart_items')->updateOrInsert(['user_id' => $user->id, 'product_variant_id' => $l['variantId']], ['qty' => max($current, $l['qty'])]);
            }
        });

        return $this->show($request);
    }

    public function replace(Request $request): JsonResponse
    {
        $data = $request->validate($this->lineRules());
        $user = $request->user();
        DB::transaction(function () use ($data, $user) {
            DB::table('cart_items')->where('user_id', $user->id)->delete();
            $valid = ProductVariant::query()->whereIn('id', collect($data['lines'])->pluck('variantId'))->pluck('id')->all();
            DB::table('cart_items')->insert(collect($data['lines'])->filter(fn ($l) => in_array($l['variantId'], $valid, true))
                ->map(fn ($l) => ['user_id' => $user->id, 'product_variant_id' => $l['variantId'], 'qty' => $l['qty']])->values()->all());
        });

        return $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('cart_items')->where('user_id', $request->user()->id)
            ->get()->map(fn ($r) => ['variantId' => (int) $r->product_variant_id, 'qty' => (int) $r->qty])]);
    }
}
