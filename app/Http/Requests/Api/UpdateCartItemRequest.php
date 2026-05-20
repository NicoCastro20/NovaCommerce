<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer'  => 'La cantidad debe ser un número entero.',
            'quantity.min'      => 'La cantidad debe ser al menos 1.',
            'quantity.max'      => 'La cantidad máxima por producto es 99.',
        ];
    }
}
