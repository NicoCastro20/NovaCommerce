<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['usuario', 'admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string'],
            'price'       => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'condition'   => ['required', Rule::in(['nuevo', 'como_nuevo', 'buen_estado', 'usado'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'El nombre del producto es obligatorio.',
            'name.min'             => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'             => 'El nombre no puede superar los 200 caracteres.',
            'description.required' => 'La descripción es obligatoria.',
            'price.required'       => 'El precio es obligatorio.',
            'price.numeric'        => 'El precio debe ser un número.',
            'price.min'            => 'El precio debe ser mayor o igual a 0,01.',
            'category_id.required' => 'Debes seleccionar una categoría.',
            'category_id.exists'   => 'La categoría seleccionada no existe.',
            'condition.required'   => 'Debes indicar el estado del producto.',
            'condition.in'         => 'Estado inválido. Valores permitidos: nuevo, como_nuevo, buen_estado, usado.',
        ];
    }
}
