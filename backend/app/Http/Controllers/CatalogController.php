<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function __construct(private CatalogPresenter $presenter) {}

    /** Снимок каталога для пререндера (CI). Кешируется до изменения каталога. */
    public function export(): JsonResponse
    {
        return response()->json(Cache::remember('catalog:export', 600, fn () => $this->presenter->export()));
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->where('is_active', true)->orderBy('sort')->get()->map($this->presenter->category(...))]);
    }

    /** Список с поиском (FULLTEXT), фильтрами, сортировкой и пагинацией */
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'pp' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:default,price_asc,price_desc,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $q = Product::query()->active()->with(['category', 'variants', 'media', 'approvedReviews'])
            ->when($data['q'] ?? null, fn ($w, $term) => $w->search($term))
            ->when($data['category'] ?? null, fn ($w, $slug) => $w->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when(isset($data['pp']), fn ($w) => $w->where('is_pp', (bool) $data['pp']));
        $q = match ($data['sort'] ?? 'default') {
            'price_asc' => $q->orderBy(ProductVariant::query()->selectRaw('min(price)')->whereColumn('product_id', 'products.id')),
            'price_desc' => $q->orderByDesc(ProductVariant::query()->selectRaw('min(price)')->whereColumn('product_id', 'products.id')),
            'name' => $q->orderBy('name'),
            default => $q->orderBy('sort')->orderBy('id'),
        };
        $page = $q->paginate($data['per_page'] ?? 24);

        return response()->json([
            'data' => collect($page->items())->map($this->presenter->product(...)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** Живые цена и остаток для карточки (после гидратации статики) */
    public function product(string $slug): JsonResponse
    {
        $p = Product::query()->active()->where('slug', $slug)->with(['category', 'variants', 'media', 'approvedReviews'])->firstOrFail();

        return response()->json(['data' => $this->presenter->product($p)])->header('Cache-Control', 'public, max-age=30');
    }
}
