<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255', 'phone' => 'required|string|max:40', 'email' => 'nullable|email|max:255',
            'delivery_method' => 'required|in:pickup,transport', 'city' => 'nullable|required_if:delivery_method,transport|string|max:255',
            'carrier' => 'nullable|string|max:255', 'destination' => 'nullable|string|max:1000', 'delivery_notes' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:3000',
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Indica tu nombre.', 'phone.required' => 'Indica un teléfono de contacto.', 'city.required_if' => 'Para transporte necesitamos ciudad o comuna de destino.'];
    }
}
