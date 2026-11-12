<x-layout :title="$product->name.' · Cubiertas Temuco'">
    <a href="{{ route('catalog.index') }}">← Volver al catálogo</a>
    <section class="grid two section">
        <div class="product-gallery">
            @forelse($product->images as $image)<img class="finish-image" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt }}" width="800" height="550" loading="lazy">@empty<x-product-image :product="$product" />@endforelse
        </div>
        <div class="shop-heading"><p class="eyebrow">{{ $product->code }}</p><h1>{{ $product->name }}</h1><p>{{ $product->description }}</p><dl class="summary-facts"><div><dt>Color</dt><dd>{{ $product->color ?: 'Por confirmar' }}</dd></div><div><dt>Terminación</dt><dd>{{ $product->finish ?: 'Por confirmar' }}</dd></div></dl><p class="cart-notes">{{ $product->characteristics }}</p><p class="muted">Actualizado: {{ $product->updated_at->format('d/m/Y') }}</p>
            @if($product->demonstration)<p class="notice">Acabado ficticio de demostración, sin precio ni disponibilidad comercial confirmados.</p>@endif
            @if($product->available)<a class="button" href="{{ route('configurator', ['product' => $product->id]) }}">Cotizar este acabado <x-icon name="arrow" /></a>@else<p class="badge pending">No disponible para cotizar</p><p><a href="{{ route('configurator', ['special'=>1]) }}">Solicitar una alternativa al taller</a></p>@endif
        </div>
    </section>
</x-layout>
