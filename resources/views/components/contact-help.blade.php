@props(['contact' => []])
<aside class="quote-help">
    <section class="help-card">
        <h2>Contáctanos y recibe asesoría</h2>
        @foreach(['whatsapp' => ['WhatsApp', 'chat', 'Conversa con el taller'], 'instagram' => ['Instagram', 'instagram', 'Conoce nuestros trabajos'], 'facebook' => ['Facebook', 'facebook', 'Visita nuestra página']] as $key => [$label, $icon, $description])
            @php($url = $contact[$key] ?? null)
            @if($url && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://'))
                <a class="contact-item" href="{{ $url }}" target="_blank" rel="noopener noreferrer"><x-icon :name="$icon" /><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><x-icon name="arrow" /></a>
            @else
                <div class="contact-item contact-pending"><x-icon :name="$icon" /><span><strong>{{ $label }}</strong><small>Pendiente de configuración</small></span></div>
            @endif
        @endforeach
        @if(filter_var($contact['email'] ?? null, FILTER_VALIDATE_EMAIL))
            <a class="contact-item" href="mailto:{{ $contact['email'] }}"><x-icon name="mail" /><span><strong>Correo electrónico</strong><small>{{ $contact['email'] }}</small></span></a>
        @else
            <div class="contact-item contact-pending"><x-icon name="mail" /><span><strong>Correo electrónico</strong><small>Pendiente de configuración</small></span></div>
        @endif
        <div class="contact-item"><x-icon name="pin" /><span><strong>Ubicación</strong><small>{{ $contact['address'] ?? 'Pendiente de configuración' }}</small>@if(filled($contact['map_url'] ?? null) && filter_var($contact['map_url'], FILTER_VALIDATE_URL) && str_starts_with($contact['map_url'], 'https://'))<a href="{{ $contact['map_url'] }}" target="_blank" rel="noopener noreferrer">Ver ubicación</a>@endif</span></div>
        <div class="contact-item"><x-icon name="clock" /><span><strong>Horario</strong><small>{{ $contact['hours'] ?? 'Pendiente de configuración' }}</small></span></div>
    </section>
    <section class="help-card guide-card"><h2>Guía de medidas</h2><x-measure-guide /><p>Distingue largo y ancho antes de configurar tu cubierta.</p><a href="{{ route('guide') }}">Ver guía completa <x-icon name="arrow" /></a></section>
</aside>
