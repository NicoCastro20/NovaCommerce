<?php

namespace App\Http\Requests\Api;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
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
            'reason'      => ['required', Rule::in(ReturnRequest::MOTIVOS)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required'    => 'Debes seleccionar un motivo de devolución.',
            'reason.in'          => 'El motivo seleccionado no es válido.',
            'description.string' => 'La descripción debe ser un texto.',
            'description.max'    => 'La descripción no puede superar los 1000 caracteres.',
        ];
    }
}
