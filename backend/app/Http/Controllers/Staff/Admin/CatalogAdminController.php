<?php

namespace App\Http\Controllers\Staff\Admin;

use App\Events\CatalogChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Админка каталога. Каждое изменение → CatalogChanged → пересборка статики (дебаунс / сразу при смене цены). */
class CatalogAdminController extends Controller
{
    public function __construct(private CatalogPresenter $presenter) {}

    public function products(): JsonResponse
    {
        return response()->json(['data' => Product::query()->with(['category', 'variants', 'media'])->orderBy('category_id')->orderBy('sort')->get()
            ->map(fn (Product $p) => $this->presenter->product($p) + ['isActive' => $p->is_active, 'categoryId' => $p->category_id])]);
    }

    public function storeProduct(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(fn () => $this->save(new Product, $request->validated()));

        return response()->json(['data' => $this->presenter->product($product)], 201);
    }

    public function updateProduct(ProductRequest $request, Product $product): JsonResponse
    {
        $oldPath = CatalogPresenter::productPath($product->load('category'));
        $oldPrices = $product->variants->pluck('price', 'id')->all();
        $product = DB::transaction(fn () => $this->save($product, $request->validated()));
        $newPrices = $product->variants->pluck('price', 'id')->all();
        $this->changed([$oldPath, CatalogPresenter::productPath($product), "/catalog/{$product->category->slug}/", '/catalog/', '/'], $oldPrices != $newPrices);

        return response()->json(['data' => $this->presenter->product($product)]);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $path = CatalogPresenter::productPath($product->load('category'));
        // Товары с заказами не удаляем, а снимаем с продажи (страница отдаст 404 после пересборки)
        $product->update(['is_active' => false]);
        $this->changed([$path, "/catalog/{$product->category->slug}/", '/catalog/'], deleted: true);

        return response()->json(null, 204);
    }

    /** Быстрое изменение остатка/цены варианта из списка */
    public function updateVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate(['price' => ['sometimes', 'integer', 'min:0'], 'stock' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        $priceChanged = isset($data['price']) && $data['price'] !== $variant->price;
        $variant->update($data);
        $product = $variant->product()->with('category')->first();
        $this->changed([CatalogPresenter::productPath($product), "/catalog/{$product->category->slug}/"], $priceChanged || isset($data['is_active']));

        return response()->json(['data' => $variant->fresh()]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->orderBy('sort')->get()]);
    }

    public function saveCategory(Request $request, ?Category $category = null): JsonResponse
    {
        $category ??= new Category;
        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category->id)],
            'name' => ['required', 'string', 'max:190'],
            'intro' => ['nullable', 'string', 'max:300'],
            'sort' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'cross_listed' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_h1' => ['nullable', 'string', 'max:190'],
            'seo_text' => ['nullable', 'string', 'max:10000'],
            'noindex' => ['boolean'],
        ]);
        $old = $category->slug;
        $category->fill($data + ['intro' => ''])->save();
        $this->changed(array_filter(["/catalog/{$category->slug}/", $old ? "/catalog/{$old}/" : null, '/catalog/', '/']), $old !== null && $old !== $category->slug);

        return response()->json(['data' => $category], $category->wasRecentlyCreated ? 201 : 200);
    }

    private function save(Product $product, array $data): Product
    {
        $product->fill(collect($data)->except(['media', 'variants'])->all())->save();
        if (array_key_exists('media', $data)) {
            $product->media()->delete();
            foreach (array_values($data['media'] ?? []) as $i => $m) {
                $product->media()->create($m + ['sort' => $i]);
            }
        }
        $keep = [];
        foreach (array_values($data['variants']) as $i => $v) {
            $variant = isset($v['id']) ? $product->variants()->whereKey($v['id'])->first() : null;
            $variant ??= new ProductVariant(['product_id' => $product->id]);
            $variant->fill(collect($v)->except('id')->all() + ['sort' => $i, 'product_id' => $product->id])->save();
            $keep[] = $variant->id;
        }
        // Удалённые из формы варианты снимаются с продажи (на них могут ссылаться заказы)
        $product->variants()->whereNotIn('id', $keep)->update(['is_active' => false]);

        return $product->refresh()->load(['category', 'variants', 'media']);
    }

    /** @param list<string> $paths */
    private function changed(array $paths, bool $priceChanged = false, bool $deleted = false): void
    {
        Cache::forget('catalog:export');
        CatalogChanged::dispatch(array_values(array_unique($paths)), $priceChanged, $deleted);
    }
}
