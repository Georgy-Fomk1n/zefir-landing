<?php

namespace App\Http\Requests;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourierTransitionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DeliveryStatus::class)],
            'reason' => ['nullable', 'string', 'max:300'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'happenedAt' => ['nullable', 'date', 'before_or_equal:now'],
            'clientActionId' => ['nullable', 'string', 'max:64'],
            'codMethod' => ['nullable', Rule::in(['cash', 'card'])],
            'codAmount' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ];
    }
}
