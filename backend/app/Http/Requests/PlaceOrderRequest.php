<?php

namespace App\Http\Requests;

use App\Enums\Fulfilment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    use CartLinesRules;

    public function rules(): array
    {
        $needsAddress = $this->input('fulfilment') !== Fulfilment::Pickup->value;

        return $this->lineRules() + [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[78][\d\s()-]{9,}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
            'slotId' => ['nullable', 'integer'],
            'address' => [$needsAddress ? 'required' : 'nullable', 'array'],
            'address.text' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:300'],
            'address.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address.entrance' => ['nullable', 'string', 'max:20'],
            'address.floor' => ['nullable', 'string', 'max:20'],
            'address.intercom' => ['nullable', 'string', 'max:40'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'promoCode' => ['nullable', 'string', 'max:64'],
            'points' => ['nullable', 'integer', 'min:0'],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['consent.accepted' => 'Нужно согласие на обработку персональных данных', 'address.text.required' => 'Укажите адрес'];
    }
}
