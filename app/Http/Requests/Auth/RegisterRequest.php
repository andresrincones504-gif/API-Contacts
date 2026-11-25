<?php

namespace App\Http\Requests\Auth;

use App\Rules\UniqueIdentity;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {

        $this->merge([
            'phone_number' => $this->input('phone_number', $this->input('numero')),
            'device_name' => $this->input('device_name', 'app'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new UniqueIdentity('email')],
            'phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/', new UniqueIdentity('phone_number')],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['required', 'string', 'max:100'],
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
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'device_name.required' => 'El nombre del dispositivo es obligatorio.',
        ];
    }
}