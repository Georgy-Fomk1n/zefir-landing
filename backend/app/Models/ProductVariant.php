<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'name', 'pack_size', 'price', 'price_from', 'stock', 'reserved', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'price_from' => 'boolean', 'stock' => 'integer', 'reserved' => 'integer', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function available(): int
    {
        return max(0, $this->stock - $this->reserved);
    }
}
