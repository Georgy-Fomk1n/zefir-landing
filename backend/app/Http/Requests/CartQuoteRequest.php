<?php

namespace App\Http\Requests;

use App\Enums\Fulfilment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartQuoteRequest extends FormRequest
{
    use CartLinesRules;

    public function rules(): array
    {
        return $this->lineRules() + [
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
            'promoCode' => ['nullable', 'string', 'max:64'],
            'points' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'array'],
            'address.text' => ['nullable', 'string', 'max:300'],
            'address.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
