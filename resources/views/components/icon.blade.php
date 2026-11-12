@props(['name'])
@php
    $paths = [
        'cart' => 'M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L22 7H6M10 21h.01M18 21h.01',
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'plus' => 'M12 5v14M5 12h14', 'minus' => 'M5 12h14',
        'check' => 'm5 12 4 4L19 6', 'menu' => 'M4 6h16M4 12h16M4 18h16',
        'edit' => 'm16 3 5 5-13 13H3v-5L16 3Z',
        'trash' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
        'image' => 'M3 3h18v18H3zM3 16l5-5 4 4 3-3 6 6M15 7h.01',
        'mail' => 'M3 5h18v14H3zM3 5l9 7 9-7',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0ZM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
        'clock' => 'M12 8v5l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
        'chat' => 'M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-1l-5 2 2-5a10 10 0 0 1-1-5 8.5 8.5 0 0 1 17 0Z',
        'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM17.5 6.5h.01',
        'facebook' => 'M14 21v-8h3l1-4h-4V7c0-1 1-2 2-2h2V2h-3c-4 0-5 3-5 5v2H7v4h3v8',
        'ruler' => 'm3 15 12-12 6 6L9 21l-6-6ZM7 11l3 3M11 7l3 3M15 3l3 3',
        'info' => 'M12 11v6M12 7h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
        'box' => 'm3 7 9-4 9 4v11l-9 4-9-4V7ZM3 7l9 4 9-4M12 11v11',
        'truck' => 'M1 4h14v13H1zM15 9h4l4 5v3h-8M7 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM21 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['info'] }}" /></svg>
