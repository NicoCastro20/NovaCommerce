<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone'    => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'      => 'El campo nombre es obligatorio.',
            'name.string'        => 'El campo nombre debe ser una cadena de texto.',
            'name.max'           => 'El nombre no puede superar los 255 caracteres.',
            'email.required'     => 'El campo correo electrónico es obligatorio.',
            'email.email'        => 'Debes introducir un correo electrónico válido.',
            'email.unique'       => 'Ya existe una cuenta con ese correo electrónico.',
            'email.max'          => 'El correo no puede superar los 255 caracteres.',
            'password.required'  => 'El campo contraseña es obligatorio.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.letters'   => 'La contraseña debe contener al menos una letra.',
            'password.numbers'   => 'La contraseña debe contener al menos un número.',
            'phone.max'          => 'El teléfono no puede superar los 20 caracteres.',
        ];
    }
}
