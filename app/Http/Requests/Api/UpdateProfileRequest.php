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
            // Avatar como archivo subido desde el dispositivo (máx. 2 MB).
            // Los administradores no pueden cambiar avatar: lo bloquea el controlador.
            'avatar' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
            'avatar.file'   => 'El avatar debe ser un archivo.',
            'avatar.image'  => 'El avatar debe ser una imagen.',
            'avatar.mimes'  => 'El avatar debe ser JPG, PNG o WebP.',
            'avatar.max'    => 'El avatar no puede superar los 2 MB.',
        ];
    }
}
