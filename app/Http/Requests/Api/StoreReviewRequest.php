<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'La puntuación es obligatoria.',
            'rating.integer'  => 'La puntuación debe ser un número entero.',
            'rating.min'      => 'La puntuación mínima es 1.',
            'rating.max'      => 'La puntuación máxima es 5.',
            'comment.max'     => 'El comentario no puede superar los 1000 caracteres.',
        ];
    }
}
