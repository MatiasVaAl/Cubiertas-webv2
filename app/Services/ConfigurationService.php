<?php

namespace App\Services;

use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\WorkshopSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigurationService
{
    public function normalize(array $input): array
    {
        $data = Validator::make($input, [
            'kind' => 'required|in:standard,special',
            'product_id' => 'nullable|required_if:kind,standard|integer|exists:products,id',
            'material_name' => 'nullable|required_if:kind,special|string|max:255',
            'material_description' => 'nullable|string|max:3000',
            'length_cm' => ['required', 'regex:/^\d{1,10}([.,]\d{1,2})?$/'],
            'width_cm' => ['required', 'regex:/^\d{1,10}([.,]\d{1,2})?$/'],
            'quantity' => 'required|integer|min:1|max:10000',
            'additional_ids' => 'sometimes|array|max:30',
            'additional_ids.*' => 'integer|distinct|exists:additional_works,id',
            'notes' => 'nullable|string|max:3000',
            'reference_path' => 'nullable|string|max:255',
        ], [
            'length_cm.regex' => 'El largo debe ser numérico y tener hasta dos decimales.',
            'width_cm.regex' => 'El ancho debe ser numérico y tener hasta dos decimales.',
            'quantity.min' => 'La cantidad debe ser al menos una cubierta.',
        ])->validate();

        foreach (['length_cm', 'width_cm'] as $dimension) {
            $data[$dimension] = bcadd(str_replace(',', '.', (string) $data[$dimension]), '0', 2);
            if (bccomp($data[$dimension], '0', 2) <= 0) {
                throw ValidationException::withMessages([$dimension => 'La medida debe ser mayor que cero.']);
            }
            $limits = WorkshopSetting::current()->dimension_limits ?? [];
            foreach (['min' => -1, 'max' => 1] as $limit => $comparison) {
                $bound = $limits[$dimension.'_'.$limit] ?? null;
                if ($bound !== null && bccomp($data[$dimension], (string) $bound, 2) === $comparison) {
                    throw ValidationException::withMessages([$dimension => 'La medida está fuera de los límites confirmados por el taller.']);
                }
            }
        }
        $product = $data['kind'] === 'standard' ? Product::findOrFail($data['product_id']) : null;
        if ($product && (! $product->available || ($product->demonstration && app()->isProduction()))) {
            throw ValidationException::withMessages(['product_id' => 'El acabado no está habilitado para solicitar cotización.']);
        }
        $ids = array_map('intval', $data['additional_ids'] ?? []);
        sort($ids);
        if (AdditionalWork::whereIn('id', $ids)->where('active', false)->exists()) {
            throw ValidationException::withMessages(['additional_ids' => 'Uno de los trabajos seleccionados no está habilitado.']);
        }

        return [
            'kind' => $data['kind'], 'product_id' => $product?->id,
            'material_name' => $product ? null : trim($data['material_name']),
            'material_description' => $product ? null : trim($data['material_description'] ?? ''),
            'length_cm' => $data['length_cm'], 'width_cm' => $data['width_cm'],
            'quantity' => (int) $data['quantity'], 'additional_ids' => $ids,
            'notes' => trim($data['notes'] ?? ''), 'reference_path' => $data['reference_path'] ?? null,
        ];
    }

    public function fingerprint(array $configuration): string
    {
        unset($configuration['quantity']);

        return hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
    }
}
