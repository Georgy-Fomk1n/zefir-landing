<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Cart;
use App\Support\OrderNotifier;
use App\Support\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function schedule(): JsonResponse
    {
        return response()->json(Schedule::data())->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, Cart $cart): JsonResponse
    {
        $lines = $cart->lines();
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'Корзина пуста']);
        }

        $data = $request->validate([
            'ship' => 'required|in:pickup,courier',
            'addr' => 'nullable|string|max:200|required_if:ship,courier|min:5',
            'name' => 'required|string|max:60',
            'phone' => 'required|string|max:30',
            'comment' => 'nullable|string|max:200',
            'date' => 'required|date_format:Y-m-d',
            'hour' => 'required|integer',
        ], [
            'addr.required_if' => 'Укажите адрес доставки', 'addr.min' => 'Укажите адрес доставки',
            'name.required' => 'Введите имя',
            'phone.required' => 'Номер неполный: нужно 10 цифр после +7',
        ]);

        $digits = preg_replace('/\D/', '', $data['phone']);
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            throw ValidationException::withMessages(['phone' => 'Номер неполный: нужно 10 цифр после +7']);
        }
        if (! Schedule::valid($data['date'], (int) $data['hour'])) {
            throw ValidationException::withMessages(['slot' => 'Это время уже недоступно. Выберите другое.']);
        }

        $fee = $data['ship'] === 'courier' ? config('shop.courier_fee') : 0;
        $subtotal = array_sum(array_column($lines, 'sum'));
        $card = $cart->card();

        $order = DB::transaction(function () use ($data, $lines, $digits, $fee, $subtotal, $card) {
            $order = Order::create([
                'token' => Str::random(32),
                'name' => trim($data['name']),
                'phone' => sprintf('+7 (%s) %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2)),
                'shipping' => $data['ship'],
                'address' => $data['ship'] === 'courier' ? trim($data['addr']) : config('shop.address'),
                'slot_date' => $data['date'], 'slot_hour' => (int) $data['hour'],
                'comment' => $data['comment'] ?? null,
                'card_text' => $card['on'] ? $card['text'] : null,
                'subtotal' => $subtotal, 'fee' => $fee, 'total' => $subtotal + $fee,
            ]);
            foreach ($lines as $l) {
                $order->items()->create(['product_id' => $l['product_id'], 'name' => $l['name'], 'image' => $l['image'], 'price' => $l['price'], 'qty' => $l['q'], 'sum' => $l['sum']]);
            }

            return $order;
        });

        $cart->clear();
        OrderNotifier::placed($order->load('items'));

        return response()->json(['ok' => true, 'redirect' => route('order', $order->token)]);
    }
}
