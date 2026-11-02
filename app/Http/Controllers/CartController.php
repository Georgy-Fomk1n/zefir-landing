<?php

namespace App\Http\Controllers;

use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Cart $cart): JsonResponse
    {
        // Заодно отдаём CSRF-токен: публичные страницы работают без сессии и токена не имеют
        return $this->out($cart);
    }

    public function update(Request $request, Cart $cart): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:add,set,card,clear',
            'id' => 'nullable|string|max:80',
            'v' => 'nullable|integer|min:0',
            'q' => 'nullable|integer|min:0|max:99',
            'on' => 'nullable|boolean',
            'text' => 'nullable|string|max:200',
            'card_text' => 'nullable|string|max:200',
        ]);

        switch ($data['action']) {
            case 'add':
                if (! $cart->add((string) ($data['id'] ?? ''), (int) ($data['v'] ?? 0), (int) ($data['q'] ?? 1))) {
                    return response()->json(['ok' => false, 'error' => 'not_buyable'], 422);
                }
                if (trim((string) ($data['card_text'] ?? '')) !== '') {
                    $cart->setCard(true, $data['card_text']);
                }
                break;
            case 'set':
                $cart->set((string) ($data['id'] ?? ''), (int) ($data['v'] ?? 0), (int) ($data['q'] ?? 0));
                break;
            case 'card':
                $cart->setCard((bool) ($data['on'] ?? false), (string) ($data['text'] ?? ''));
                break;
            case 'clear':
                $cart->clear();
                break;
        }

        return $this->out($cart);
    }

    private function out(Cart $cart): JsonResponse
    {
        return response()->json(['ok' => true, 'cart' => $cart->state(), 'csrf' => csrf_token()])
            ->header('Cache-Control', 'no-store');
    }
}
