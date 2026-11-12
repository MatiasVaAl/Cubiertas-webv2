<?php

namespace App\Services\Pricing;

use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\WorkshopSetting;
use App\Services\ConfigurationService;
use App\Support\Clp;

class PricingService
{
    public function __construct(private ConfigurationService $configurations, private PriceStrategy $strategy) {}

    public function calculate(array $input): array
    {
        $configuration = $this->configurations->normalize($input);
        $product = $configuration['product_id'] ? Product::findOrFail($configuration['product_id']) : null;
        $pending = [];
        $unit = null;
        $rate = null;
        if (! $product) {
            $pending[] = 'Material especial: disponibilidad y precio por confirmar.';
        } elseif ($product->demonstration) {
            $pending[] = 'Acabado demostrativo; no es una oferta comercial.';
        } elseif (! WorkshopSetting::current()->formula_confirmed) {
            $pending[] = 'Fórmula comercial pendiente de confirmación.';
        } else {
            $rate = $product->rates()->where('active', true)->where('confirmed', true)
                ->where('min_width_cm', '<=', $configuration['width_cm'])
                ->where('max_width_cm', '>', $configuration['width_cm'])->first();
            if ($rate) {
                $unit = $this->strategy->unitPrice($configuration['length_cm'], $configuration['width_cm'], $rate->price_per_m2_clp);
            } else {
                $pending[] = 'No existe una tarifa confirmada para este ancho.';
            }
        }
        $works = AdditionalWork::whereIn('id', $configuration['additional_ids'])->orderBy('id')->get();
        $knownExtras = 0;
        foreach ($works as $work) {
            if ($work->confirmed && $work->pricing_mode === 'per_unit' && $work->price_clp !== null) {
                $knownExtras += $work->price_clp;
            } else {
                $pending[] = $work->name.': precio pendiente de revisión.';
            }
        }
        $knownUnit = ($unit ?? 0) + $knownExtras;
        $knownSubtotal = Clp::multiply($knownUnit, $configuration['quantity']);

        return [
            'configuration' => $configuration,
            'name' => $product?->name ?? $configuration['material_name'],
            'product_code' => $product?->code,
            'additional_works' => $works->map(fn ($work) => $work->only(['id', 'name', 'pricing_mode', 'price_clp', 'confirmed']))->all(),
            'rate' => $rate?->only(['id', 'min_width_cm', 'max_width_cm', 'price_per_m2_clp']),
            'unit_price_clp' => $pending ? null : $knownUnit,
            'known_subtotal_clp' => $knownSubtotal,
            'subtotal_clp' => $pending ? null : $knownSubtotal,
            'pending_reasons' => $pending,
        ];
    }
}
