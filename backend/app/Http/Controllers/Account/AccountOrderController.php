<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with(['items', 'history', 'slot', 'delivery.history'])->latest()->limit(100)->get();

        return response()->json(['data' => $orders->map(fn ($o) => OrderPresenter::make($o, $request->user()))]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view-order', $order);

        return response()->json(['data' => OrderPresenter::make($order, $request->user())]);
    }

    /** Отзыв: только покупатель выданного заказа, один на товар в заказе, с модерацией. */
    public function review(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view-order', $order);
        $data = $request->validate([
            'productId' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'text' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['order' => 'Отзыв можно оставить после получения заказа']);
        }
        if (! $order->items()->where('product_id', $data['productId'])->exists()) {
            throw ValidationException::withMessages(['productId' => 'Этого товара нет в заказе']);
        }
        if (Review::query()->where('order_id', $order->id)->where('product_id', $data['productId'])->exists()) {
            throw ValidationException::withMessages(['productId' => 'Отзыв уже оставлен']);
        }
        $review = Review::create([
            'product_id' => $data['productId'], 'user_id' => $request->user()->id, 'order_id' => $order->id,
            'author_name' => mb_substr($request->user()->name, 0, 40), 'rating' => $data['rating'], 'text' => strip_tags($data['text']), 'status' => 'pending',
        ]);

        return response()->json(['data' => ['id' => $review->id, 'status' => $review->status]], 201);
    }
}
