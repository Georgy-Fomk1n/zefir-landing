<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected static function booted(): void
    {
        // Любая правка товара сбрасывает кэш публичных страниц
        foreach (['saved', 'deleted'] as $ev) {
            static::$ev(fn () => \App\Http\Middleware\PageCache::flush());
        }
        // Новое фото из админки → делаем лёгкие версии
        static::saved(function (Product $p) {
            if ($p->wasChanged('upload') && $p->upload) {
                \App\Support\ProductImages::process($p);
            }
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_from' => 'bool', 'out_of_stock' => 'bool', 'on_order' => 'bool', 'is_new' => 'bool', 'is_pp' => 'bool',
            'active' => 'bool', 'image' => 'array', 'price' => 'integer',
        ];
    }

    public const CATEGORIES = [
        'all' => 'Все', 'cake' => 'Пирожные', 'zefir' => 'Зефир', 'pryanik' => 'Пряники',
        'tort' => 'Торты', 'gift' => 'Подарки', 'pp' => 'Без сахара',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('active', true)->orderBy('sort')->orderBy('id');
    }

    public function scopeInCategory(Builder $q, string $cat): Builder
    {
        return match ($cat) {
            'all' => $q,
            'gift' => $q->whereIn('category', ['gift', 'pryanik']),
            'pp' => $q->where('is_pp', true),
            default => $q->where('category', $cat),
        };
    }

    /** Можно положить в корзину */
    public function isBuyable(): bool
    {
        return $this->active && is_int($this->price) && ! $this->out_of_stock && ! $this->on_order;
    }

    /** Форматы товара. Если форматов нет, один «формат» с ценой товара (id = 0). */
    public function variantList(): array
    {
        if ($this->variants->isNotEmpty()) {
            return $this->variants->map(fn ($v) => ['id' => $v->id, 'label' => $v->label, 'sub' => (string) $v->sub, 'price' => $v->price])->all();
        }

        return [['id' => 0, 'label' => $this->name, 'sub' => '', 'price' => (int) $this->price]];
    }

    public function variant(?int $id): array
    {
        $list = $this->variantList();
        foreach ($list as $v) {
            if ($v['id'] === (int) $id) {
                return $v;
            }
        }

        return $list[0];
    }

    public function url(): string
    {
        return route('product', $this->slug);
    }

    public function priceText(): string
    {
        if ($this->out_of_stock) {
            return 'Нет в наличии';
        }
        if ($this->price_note) {
            return $this->price_note;
        }
        if (! is_int($this->price)) {
            return 'Цена по запросу';
        }

        return ($this->price_from ? "от\u{00A0}" : '') . rub($this->price);
    }
}
