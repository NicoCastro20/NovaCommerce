<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['empresa', 'usuario', 'admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images'   => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Debes adjuntar al menos una imagen.',
            'images.array'    => 'El campo imágenes debe ser un listado.',
            'images.min'      => 'Debes adjuntar al menos una imagen.',
            'images.max'      => 'No puedes subir más de 5 imágenes a la vez.',
            'images.*.required' => 'Cada archivo es obligatorio.',
            'images.*.image'    => 'Cada archivo debe ser una imagen válida.',
            'images.*.mimes'    => 'Las imágenes deben ser jpg, jpeg, png o webp.',
            'images.*.max'      => 'Cada imagen no puede superar los 4 MB.',
        ];
    }
}
