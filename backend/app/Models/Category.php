<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasSeo;

    protected $fillable = ['slug', 'name', 'intro', 'sort', 'cross_listed', 'items', 'is_active',
        'seo_title', 'seo_description', 'seo_h1', 'seo_text', 'noindex'];

    protected function casts(): array
    {
        return ['cross_listed' => 'array', 'items' => 'array', 'is_active' => 'boolean', 'noindex' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
