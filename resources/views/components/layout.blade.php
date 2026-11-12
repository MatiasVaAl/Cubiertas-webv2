<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ?? 'Cubiertas Temuco Digital' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header shop-header">
    <div class="shell header-inner">
        <a href="{{ route('home') }}" class="brand"><span class="brand-mark" aria-hidden="true">CT</span><span>Cubiertas Temuco<small>Diseñadas para tu espacio</small></span></a>
        <nav class="public-nav" aria-label="Navegación principal"><x-public-links /></nav>
        <livewire:cart-counter />
        <details class="mobile-menu"><summary aria-label="Abrir menú de navegación"><x-icon name="menu" /></summary><nav aria-label="Navegación móvil"><x-public-links /></nav></details>
    </div>
</header>
@auth
<div class="staff-bar"><div class="shell"><a href="{{ route('admin.dashboard') }}">Panel administrativo</a><span>{{ auth()->user()->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button>Cerrar sesión</button></form></div></div>
@endauth
<main id="contenido" class="shell main-content {{ request()->is('admin*') ? '' : 'shop-main' }}">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    {{ $slot }}
</main>
<footer><div class="shell"><strong>Cubiertas Temuco Digital</strong><p>Cubiertas a medida · Solicitudes y seguimiento en línea</p><a href="{{ route('login') }}">Acceso del equipo</a></div></footer>
@livewireScripts
</body>
</html>
