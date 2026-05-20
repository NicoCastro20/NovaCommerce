<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(['pendiente', 'pagado', 'enviado', 'entregado', 'cancelado', 'devuelto'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Debes indicar el nuevo estado.',
            'status.in'       => 'El estado no es válido. Valores permitidos: pendiente, pagado, enviado, entregado, cancelado, devuelto.',
        ];
    }
}
