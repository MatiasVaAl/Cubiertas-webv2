<x-layout title="Nuestro catálogo · Cubiertas Temuco">
    <header class="shop-heading"><p class="eyebrow">EL PUNTO DE PARTIDA DE TU PROYECTO</p><h1>Nuestro catálogo</h1><p>Encuentra el acabado ideal y configura tus cubiertas a medida.</p></header>
    <form method="GET" class="shop-toolbar"><label for="catalog-search">Buscar acabado<input id="catalog-search" name="q" value="{{ $search }}" placeholder="Nombre o código"></label><button class="button secondary">Buscar</button>@if($search)<a href="{{ route('catalog.index') }}">Limpiar búsqueda</a>@endif</form>
    <div class="catalog-grid">
        @forelse($products as $product)
            <x-finish-card :product="$product" />
        @empty
            <div class="empty-hint">{{ $search ? 'No encontramos acabados para esa búsqueda. Prueba otro nombre o código.' : 'El taller aún no ha publicado acabados. Puedes solicitar un material especial.' }}</div>
        @endforelse
    </div>
    {{ $products->links() }}
    <section class="banner section"><div><h2>¿Buscas un material diferente?</h2><p>Cuéntanos qué necesitas y solicita una evaluación personalizada.</p></div><a class="button" href="{{ route('configurator', ['special'=>1]) }}">Solicitar material especial <x-icon name="arrow" /></a></section>
</x-layout>
