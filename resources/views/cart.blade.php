<x-layout title="Mi carrito de cotización · Cubiertas Temuco">
    <header class="shop-heading"><p class="eyebrow">TU PROYECTO EN UN SOLO LUGAR</p><h1><x-icon name="cart" /> Mi carrito de cotización</h1><p>{{ trans_choice('interface.units', $summary['quantity']) }} en {{ trans_choice('interface.configurations', count($summary['lines'])) }}.</p></header>
    @if($summary['lines'])
        <div class="cart-layout">
            <section class="cart-items" aria-label="Cubiertas en tu carrito">
                @foreach($summary['lines'] as $key => $line)
                    @php($configuration = $line['configuration'])
                    <article class="cart-item">
                        <x-product-image :product="$products->get($configuration['product_id'])" />
                        <div>
                            <h2>{{ $line['name'] }}</h2>
                            <div class="cart-item-info"><p>{{ $configuration['length_cm'] }} × {{ $configuration['width_cm'] }} cm</p><p>{{ trans_choice('interface.units', $configuration['quantity']) }} · Superficie por unidad: {{ \App\Support\CoverMeasurements::display(\App\Support\CoverMeasurements::area($configuration['length_cm'], $configuration['width_cm'])) }} m²</p></div>
                            @if($configuration['kind'] === 'special')<p class="cart-notes">{{ $configuration['material_description'] }}</p>@endif
                            @if($configuration['notes'])<p class="cart-notes">{{ $configuration['notes'] }}</p>@endif
                            @if($configuration['reference_path'])<p class="muted"><x-icon name="image" /> Referencia privada adjunta</p>@endif
                            @foreach($line['additional_works'] as $work)<span class="badge">{{ $work['name'] }}</span>@endforeach
                            @if($line['pending_reasons'])
                                <p class="price-pending">Precio pendiente de revisión</p>
                                <details class="pending-details"><summary>Ver detalles de revisión</summary>@foreach($line['pending_reasons'] as $reason)<p>{{ $reason }}</p>@endforeach</details>
                            @else
                                <p class="muted">Precio unitario: ${{ number_format($line['unit_price_clp'], 0, ',', '.') }} CLP</p>
                                <p><strong>Subtotal: ${{ number_format($line['subtotal_clp'], 0, ',', '.') }} CLP</strong></p>
                            @endif
                        </div>
                        <div class="cart-item-actions">
                            <form method="POST" action="{{ route('cart.update', $key) }}">@csrf @method('PATCH')<x-quantity-stepper :value="$configuration['quantity']" :id="'quantity-'.$key" /></form>
                            <a class="button secondary" href="{{ route('cart.edit', $key) }}"><x-icon name="edit" /> Editar</a>
                            <form method="POST" action="{{ route('cart.destroy', $key) }}">@csrf @method('DELETE')<button class="button secondary" type="submit" aria-label="Eliminar {{ $line['name'] }} de {{ $configuration['length_cm'] }} por {{ $configuration['width_cm'] }} centímetros"><x-icon name="trash" /> Eliminar</button></form>
                            @if($configuration['quantity'] > 1)<form method="POST" action="{{ route('cart.split', $key) }}">@csrf<button class="split-action">Separar y editar una unidad</button></form>@endif
                        </div>
                    </article>
                @endforeach
            </section>
            <aside class="cart-summary">
                <h2>Resumen de tu cotización</h2>
                <dl class="summary-facts"><div><dt><x-icon name="box" /> Configuraciones</dt><dd>{{ count($summary['lines']) }}</dd></div><div><dt>Cubiertas en total</dt><dd>{{ $summary['quantity'] }}</dd></div></dl>
                <x-quote-amount :amount="$summary['known_subtotal_clp']" :pending="$summary['pending']" />
                <p class="review-hint"><x-icon name="truck" /> El transporte, si lo solicitas, se confirma manualmente según el destino.</p>
                <a class="button" href="{{ route('quotes.create') }}">Continuar con la solicitud <x-icon name="arrow" /></a>
                <a class="button secondary" href="{{ route('configurator') }}"><x-icon name="plus" /> Agregar otra cubierta</a>
                <p class="summary-footnote">Revisarás y aceptarás la cotización definitiva antes de pagar.</p>
            </aside>
        </div>
    @else
        <section class="empty-cart"><x-icon name="cart" /><h2>Tu carrito está vacío</h2><p>Elige un acabado o solicita un material especial para comenzar.</p><a class="button" href="{{ route('configurator') }}">Configurar mi primera cubierta <x-icon name="arrow" /></a></section>
    @endif
</x-layout>
