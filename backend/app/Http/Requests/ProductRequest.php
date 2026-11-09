<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'category_id' => ['required', 'exists:categories,id'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($id)],
            'name' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:5000'],
            'is_pp' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0'],
            'related' => ['nullable', 'array'],
            'related.*' => ['string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_h1' => ['nullable', 'string', 'max:190'],
            'seo_text' => ['nullable', 'string', 'max:10000'],
            'noindex' => ['boolean'],
            'media' => ['nullable', 'array'],
            'media.*.type' => ['required', Rule::in(['image', 'video'])],
            'media.*.name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'media.*.alt' => ['required', 'string', 'max:190'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.name' => ['required', 'string', 'max:190'],
            'variants.*.pack_size' => ['integer', 'min:1'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.price_from' => ['boolean'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ];
    }
}
