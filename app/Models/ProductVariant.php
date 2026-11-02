<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        foreach (['saved', 'deleted'] as $ev) {
            static::$ev(fn () => \App\Http\Middleware\PageCache::flush());
        }
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
