<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name'   => ['sometimes', 'required', 'string', 'max:255'],
            'phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre es obligatorio.',
            'name.string'   => 'El campo nombre debe ser una cadena de texto.',
            'name.max'      => 'El nombre no puede superar los 255 caracteres.',
            'phone.max'     => 'El teléfono no puede superar los 20 caracteres.',
            'avatar.max'    => 'La ruta del avatar no puede superar los 255 caracteres.',
        ];
    }
}
