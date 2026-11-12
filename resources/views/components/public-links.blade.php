@foreach(['home' => 'Inicio', 'catalog.index' => 'Catálogo', 'configurator' => 'Cotizar', 'guide' => 'Guía de medidas', 'tracking.lookup' => 'Consultar pedido'] as $route => $label)
    <a href="{{ route($route) }}" @if(request()->routeIs($route) || ($route === 'catalog.index' && request()->routeIs('catalog.show'))) aria-current="page" @endif>{{ $label }}</a>
@endforeach
<a href="{{ route('home') }}#contacto">Contacto</a>
