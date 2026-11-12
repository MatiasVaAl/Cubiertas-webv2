@props(['product', 'selectable' => false, 'selected' => false])
@if($selectable)
    <label class="finish-choice {{ $selected ? 'is-selected' : '' }} {{ !$product->available ? 'is-unavailable' : '' }}" wire:key="finish-{{ $product->id }}">
        <input class="finish-radio" type="radio" name="finish" wire:model.live="product_id" value="{{ $product->id }}" @disabled(!$product->available)>
        <x-product-image :product="$product" />
        <span class="finish-choice-copy"><strong>{{ $product->name }}</strong><small class="finish-choice-description">{{ Str::limit($product->description ?: 'MDF con laminado decorativo.', 65) }}</small><small>{{ $product->available ? ($product->demonstration ? 'Muestra demostrativa' : 'Para solicitar') : 'No disponible' }}</small></span>
        @if($selected)<span class="selection-check"><x-icon name="check" /><span class="sr-only">Seleccionado</span></span>@endif
    </label>
@else
    <article class="finish-tile">
        <a href="{{ route('catalog.show', $product) }}" class="finish-tile-image"><x-product-image :product="$product" /></a>
        <div class="finish-tile-copy">
            <h2><a href="{{ route('catalog.show', $product) }}">{{ $product->name }}</a></h2>
            <p>{{ Str::limit($product->description ?: 'MDF con laminado decorativo.', 105) }}</p>
            <span class="badge {{ !$product->available ? 'pending' : '' }}">{{ $product->available ? 'Habilitado para solicitud' : 'No disponible' }}</span>
            @if($product->demonstration)<small class="demo-note">Demostración · no comercial</small>@endif
            @if($product->available)<a href="{{ route('configurator', ['product' => $product->id]) }}" class="tile-action">Cotizar este acabado <x-icon name="arrow" /></a>@endif
        </div>
    </article>
@endif
