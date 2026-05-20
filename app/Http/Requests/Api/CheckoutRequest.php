<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            'shipping_address'     => ['required', 'string', 'max:500'],
            'shipping_city'        => ['required', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'max:10'],
            'shipping_country'     => ['sometimes', 'string', 'max:60'],
            'payment_method'       => ['sometimes', 'nullable', 'string', 'max:60'],
            'notes'                => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_address.required'     => 'La dirección de envío es obligatoria.',
            'shipping_address.max'          => 'La dirección no puede superar los 500 caracteres.',
            'shipping_city.required'        => 'La ciudad de envío es obligatoria.',
            'shipping_city.max'             => 'La ciudad no puede superar los 120 caracteres.',
            'shipping_postal_code.required' => 'El código postal de envío es obligatorio.',
            'shipping_postal_code.max'      => 'El código postal no puede superar los 10 caracteres.',
            'notes.max'                     => 'Las notas no pueden superar los 1000 caracteres.',
        ];
    }
}
