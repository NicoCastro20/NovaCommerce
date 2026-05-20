<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes indicar el producto.',
            'product_id.exists'   => 'El producto no existe.',
            'quantity.required'   => 'La cantidad es obligatoria.',
            'quantity.integer'    => 'La cantidad debe ser un número entero.',
            'quantity.min'        => 'La cantidad debe ser al menos 1.',
            'quantity.max'        => 'La cantidad máxima por producto es 99.',
        ];
    }
}
