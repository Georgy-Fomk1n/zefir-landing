<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\QrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function show(Request $request, LoyaltyService $loyalty, QrToken $qr): JsonResponse
    {
        $user = $request->user();
        $token = $qr->issue('loyalty', $user->id);

        return response()->json(['data' => [
            'balance' => $loyalty->balance($user),
            'maxSpendShare' => (float) config('shop.loyalty.max_spend_share'),
            'qrToken' => $token['token'],
            'qrExpiresAt' => $token['expiresAt'],
            'transactions' => $user->loyaltyTransactions()->latest('id')->limit(50)->get()->map(fn ($t) => [
                'id' => $t->id, 'type' => $t->type, 'amount' => $t->amount, 'comment' => $t->comment, 'createdAt' => $t->created_at->toIso8601String(),
            ]),
        ]]);
    }
}
