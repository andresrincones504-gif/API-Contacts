<?php

namespace App\Http\Requests\Contact;

use App\Rules\UniqueIdentity;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new UniqueIdentity('email')],
            'phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/', new UniqueIdentity('phone_number')],
            'address' => ['nullable', 'string', 'max:255'],
            'name_visible' => ['sometimes', 'boolean'],
            'address_visible' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'phone_number.required' => 'El teléfono es obligatorio.',
            'phone_number.regex' => 'El teléfono debe tener entre 7 y 15 dígitos (puede iniciar con +).',
        ];
    }
}
