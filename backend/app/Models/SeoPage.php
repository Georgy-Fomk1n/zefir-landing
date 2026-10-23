<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    use HasSeo;

    protected $fillable = ['path', 'seo_title', 'seo_description', 'seo_h1', 'seo_text', 'noindex'];

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }
}
