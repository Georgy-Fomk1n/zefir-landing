<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = DB::table('favorites')->where('user_id', $request->user()->id)->orderByDesc('created_at')->pluck('product_id');

        return response()->json(['data' => Product::query()->active()->whereIn('id', $ids)->get(['id', 'slug'])->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug])]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        DB::table('favorites')->insertOrIgnore(['user_id' => $request->user()->id, 'product_id' => $product->id, 'created_at' => now()]);

        return response()->json(null, 204);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        DB::table('favorites')->where(['user_id' => $request->user()->id, 'product_id' => $product->id])->delete();

        return response()->json(null, 204);
    }
}
