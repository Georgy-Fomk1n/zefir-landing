<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasSeo;

    protected $fillable = ['category_id', 'slug', 'name', 'description', 'is_pp', 'is_active', 'sort', 'related',
        'seo_title', 'seo_description', 'seo_h1', 'seo_text', 'noindex'];

    protected function casts(): array
    {
        return ['is_pp' => 'boolean', 'is_active' => 'boolean', 'related' => 'array', 'noindex' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Поиск: MySQL FULLTEXT (boolean mode), на других драйверах — LIKE (тесты на SQLite). */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        // Только буквы, цифры и дефис: операторы boolean mode и символы LIKE не должны попасть в запрос
        $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $term), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return $q;
        }
        $term = implode(' ', $words);
        if ($q->getConnection()->getDriverName() === 'mysql') {
            $boolean = implode(' ', array_map(fn ($w) => '+'.$w.'*', $words));

            return $q->whereFullText(['name', 'description'], $boolean, ['mode' => 'boolean']);
        }
        // SQLite не умеет lower() для кириллицы: ищем в нижнем регистре и с заглавной буквы
        $variants = array_unique([mb_strtolower($term), mb_convert_case(mb_strtolower($term), MB_CASE_TITLE)]);

        return $q->where(function ($w) use ($variants) {
            foreach ($variants as $v) {
                $w->orWhere('name', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%");
            }
        });
    }
}
