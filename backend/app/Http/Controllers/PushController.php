<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Подписки Web Push: покупатель — /api/account/push, сотрудник — /api/staff/push (область по токену). */
class PushController extends Controller
{
    public function key(): JsonResponse
    {
        return response()->json(['publicKey' => config('shop.push.public_key')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:40'],
        ]);
        $sub = PushSubscription::query()->updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'scope' => $this->scope($request),
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->json(['data' => ['id' => $sub->id]], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string', 'max:1000']])['endpoint'];
        PushSubscription::query()->where('endpoint_hash', hash('sha256', $endpoint))->where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }

    private function scope(Request $request): string
    {
        return $request->user()->tokenCan('staff') ? 'staff' : 'customer';
    }
}
