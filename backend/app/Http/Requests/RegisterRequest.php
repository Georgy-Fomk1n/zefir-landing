<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[78][\d\s()-]{9,}$/'],
            'password' => ['required', 'string', Password::min(8)],
            'consent' => ['accepted'],
            'marketingConsent' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Этот email уже зарегистрирован',
            'consent.accepted' => 'Нужно согласие на обработку персональных данных',
        ];
    }
}
