<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderController extends Controller
{
    public function show(string $token)
    {
        $order = Order::with('items')->where('token', $token)->firstOrFail();

        return response()->view('pages.order', compact('order'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
