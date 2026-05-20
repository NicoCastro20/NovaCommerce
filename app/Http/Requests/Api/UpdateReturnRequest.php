<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequest extends FormRequest
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
            'action'      => ['required', Rule::in(['aprobar', 'rechazar'])],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required'    => 'Debes indicar si apruebas o rechazas la devolución.',
            'action.in'          => 'La acción debe ser "aprobar" o "rechazar".',
            'admin_notes.string' => 'Las notas deben ser un texto.',
            'admin_notes.max'    => 'Las notas no pueden superar los 1000 caracteres.',
        ];
    }
}
