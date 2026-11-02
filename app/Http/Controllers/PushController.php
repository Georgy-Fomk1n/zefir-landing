<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:owner,customer',
            'order_token' => 'nullable|string|max:40',
            'subscription.endpoint' => 'required|url|max:1000',
            'subscription.keys.p256dh' => 'required|string|max:200',
            'subscription.keys.auth' => 'required|string|max:100',
            'subscription.contentEncoding' => 'nullable|in:aesgcm,aes128gcm',
        ]);
        $sub = $data['subscription'];

        $attrs = ['kind' => $data['kind'], 'user_id' => null, 'order_id' => null];
        if ($data['kind'] === 'owner') {
            // Подписаться на новые заказы может только вошедший в админку
            abort_unless($request->user(), 403);
            $attrs['user_id'] = $request->user()->id;
        } else {
            $order = Order::where('token', (string) ($data['order_token'] ?? ''))->first();
            abort_unless($order, 404);
            $attrs['order_id'] = $order->id;
        }

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $sub['endpoint'])],
            $attrs + [
                'endpoint' => $sub['endpoint'], 'p256dh' => $sub['keys']['p256dh'], 'auth' => $sub['keys']['auth'],
                'encoding' => $sub['contentEncoding'] ?? 'aesgcm', 'user_agent' => substr((string) $request->userAgent(), 0, 250),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
        }

        return response()->json(['ok' => true]);
    }
}
