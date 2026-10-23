<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;

/**
 * Сериализация каталога в формат packages/shared/src/types.ts (Catalog, Product, Category).
 * Используется и API, и экспортом для пререндера: фронтенд получает одинаковые структуры.
 */
class CatalogPresenter
{
    public function product(Product $p): array
    {
        $p->loadMissing(['category', 'variants', 'media']);
        $approved = $p->relationLoaded('approvedReviews') ? $p->getRelation('approvedReviews') : $p->approvedReviews()->get();

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'categorySlug' => $p->category->slug,
            'name' => $p->name,
            'description' => $p->description,
            'isPp' => $p->is_pp,
            'media' => $p->media->map(fn ($m) => ['type' => $m->type, 'name' => $m->name, 'alt' => $m->alt])->values()->all(),
            'variants' => $p->variants->where('is_active', true)->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'price' => $v->price,
                'priceFrom' => $v->price_from,
                'inStock' => $v->available() > 0,
                'stock' => $v->available(),
                'packSize' => $v->pack_size,
            ])->values()->all(),
            'rating' => $approved->count() > 0 ? ['value' => round($approved->avg('rating'), 1), 'count' => $approved->count()] : null,
            'related' => $p->related ?? [],
            'seo' => $p->seoArray(),
            'updatedAt' => $p->updated_at?->toIso8601String(),
        ];
    }

    public function category(Category $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'intro' => $c->intro,
            'sort' => $c->sort,
            'seo' => $c->seoArray(),
            'crossListed' => $c->cross_listed ?? [],
            'items' => $c->items ?? [],
            'updatedAt' => $c->updated_at?->toIso8601String(),
        ];
    }

    /** Полный снимок для сборки статики (GET /api/catalog/export) */
    public function export(): array
    {
        $products = Product::query()->active()
            ->with(['category', 'variants', 'media', 'approvedReviews'])
            ->orderBy('sort')->orderBy('id')->get();

        return [
            'generatedAt' => now()->toIso8601String(),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort')->get()->map($this->category(...))->all(),
            'products' => $products->map($this->product(...))->all(),
            'deliveryZones' => DeliveryZone::query()->where('is_active', true)->orderBy('sort')->get()->map(fn (DeliveryZone $z) => [
                'id' => $z->id, 'name' => $z->name, 'price' => $z->price, 'minOrder' => $z->min_order,
                'freeFrom' => $z->free_from, 'etaText' => $z->etaText(),
            ])->all(),
            'reviews' => Review::query()->where('status', 'approved')->with('product:id,slug')->latest()->limit(200)->get()->map(fn (Review $r) => [
                'id' => $r->id, 'author' => $r->author_name, 'rating' => $r->rating, 'text' => $r->text,
                'createdAt' => $r->created_at->toIso8601String(), 'productSlug' => $r->product->slug,
            ])->all(),
        ];
    }

    public static function productPath(Product $p): string
    {
        return "/catalog/{$p->category->slug}/{$p->slug}/";
    }
}
