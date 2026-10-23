<?php

namespace App\Models\Concerns;

trait HasSeo
{
    /** @return array{title: ?string, description: ?string, h1: ?string, text: ?string, noindex: bool} */
    public function seoArray(): array
    {
        return [
            'title' => $this->seo_title,
            'description' => $this->seo_description,
            'h1' => $this->seo_h1,
            'text' => $this->seo_text,
            'noindex' => (bool) $this->noindex,
        ];
    }
}
