<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Каталог из текущего сайта (database/data/catalog-seed.json, тексты дословно из legacy/index.html). Идемпотентен. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $seed = json_decode(file_get_contents(database_path('data/catalog-seed.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($seed) {
            $categories = [];
            foreach ($seed['categories'] as $c) {
                $categories[$c['slug']] = Category::query()->updateOrCreate(['slug' => $c['slug']], [
                    'name' => $c['name'], 'intro' => $c['intro'], 'sort' => $c['sort'],
                    'cross_listed' => $c['cross_listed'] ?? [], 'items' => $c['items'] ?? [],
                    'seo_title' => $c['seo']['title'] ?? null, 'seo_description' => $c['seo']['description'] ?? null, 'seo_h1' => $c['seo']['h1'] ?? null,
                ]);
            }
            foreach ($seed['products'] as $i => $p) {
                $product = Product::query()->updateOrCreate(['slug' => $p['slug']], [
                    'category_id' => $categories[$p['category']]->id, 'name' => $p['name'], 'description' => $p['description'],
                    'is_pp' => $p['is_pp'], 'sort' => $i * 10, 'related' => $p['related'] ?? [],
                    'seo_title' => $p['seo']['title'] ?? null, 'seo_description' => $p['seo']['description'] ?? null,
                ]);
                $product->media()->delete();
                foreach ($p['media'] as $j => $m) {
                    $product->media()->create($m + ['sort' => $j]);
                }
                foreach ($p['variants'] as $j => $v) {
                    $product->variants()->updateOrCreate(['sku' => $v['sku']], [
                        'name' => $v['name'], 'pack_size' => $v['pack_size'], 'price' => $v['price'], 'price_from' => $v['price_from'],
                        'stock' => $v['stock'], 'sort' => $j,
                    ]);
                }
            }
        });
    }
}
