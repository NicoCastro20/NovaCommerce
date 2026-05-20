<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'new_password'     => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required'         => 'Debes introducir tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'new_password.required'             => 'Debes introducir la nueva contraseña.',
            'new_password.confirmed'            => 'La confirmación de la nueva contraseña no coincide.',
            'new_password.different'            => 'La nueva contraseña debe ser distinta a la actual.',
            'new_password.min'                  => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'new_password.letters'              => 'La nueva contraseña debe contener al menos una letra.',
            'new_password.numbers'              => 'La nueva contraseña debe contener al menos un número.',
        ];
    }
}
