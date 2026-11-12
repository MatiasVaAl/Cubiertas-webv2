<x-layout title="Completa tu solicitud · Cubiertas Temuco">
    <header class="shop-heading"><p class="eyebrow">UN PASO MÁS PARA TU PROYECTO</p><h1>Completa tu solicitud</h1><p>Cuéntanos tus datos y preferencias para que el taller pueda revisar tu pedido.</p></header>
    <div class="request-layout">
        <form method="POST" action="{{ route('quotes.store') }}" class="request-card" x-data="{delivery: @js(old('delivery_method', 'pickup'))}">
            @csrf
            <h2>Tus datos</h2>
            <div class="form-stack">
                <label for="customer-name">Nombre completo <span aria-hidden="true">*</span><input id="customer-name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name') }}" aria-describedby="name-error"></label><x-field-error name="name" />
                <label for="customer-phone">Teléfono de contacto <span aria-hidden="true">*</span><input id="customer-phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" value="{{ old('phone') }}" aria-describedby="phone-error"></label><x-field-error name="phone" />
                <label for="customer-email">Correo electrónico (opcional)<input id="customer-email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}" aria-describedby="email-error"></label><x-field-error name="email" />
            </div>
            <h2>Entrega de tus cubiertas</h2>
            <div class="form-stack">
                <label for="delivery-method">Modalidad de entrega<select id="delivery-method" name="delivery_method" x-model="delivery"><option value="pickup" @selected(old('delivery_method','pickup')==='pickup')>Retiro en taller · sin cargo de transporte</option><option value="transport" @selected(old('delivery_method')==='transport')>Transporte · costo por confirmar</option></select></label>
                <x-field-error name="delivery_method" />
                <fieldset class="form-stack" x-show="delivery === 'transport'">
                    <legend>Datos de transporte</legend>
                    <p class="review-hint"><x-icon name="info" /> El taller confirmará el costo y las condiciones antes de que aceptes la cotización.</p>
                    <label for="destination-city">Ciudad o comuna <span aria-hidden="true">*</span><input id="destination-city" name="city" maxlength="255" value="{{ old('city') }}" :required="delivery === 'transport'"></label><x-field-error name="city" />
                    <label for="preferred-carrier">Empresa preferida (opcional)<input id="preferred-carrier" name="carrier" maxlength="255" value="{{ old('carrier') }}"></label>
                    <label for="delivery-destination">Sucursal o dirección de referencia<textarea id="delivery-destination" name="destination" maxlength="1000">{{ old('destination') }}</textarea></label>
                    <label for="delivery-notes">Observaciones de transporte<textarea id="delivery-notes" name="delivery_notes" maxlength="2000">{{ old('delivery_notes') }}</textarea></label>
                </fieldset>
            </div>
            <h2>Detalles de la solicitud</h2>
            <div class="form-stack"><label for="request-notes">Observaciones generales (opcional)<textarea id="request-notes" name="notes" maxlength="3000">{{ old('notes') }}</textarea></label><x-field-error name="notes" /><p class="muted">Enviar esta solicitud no inicia fabricación ni confirma un pago.</p><button class="button">Enviar solicitud de cotización <x-icon name="arrow" /></button></div>
        </form>
        <aside class="cart-summary">
            <h2>Resumen de la solicitud</h2>
            <p class="muted">{{ trans_choice('interface.covers', $summary['quantity']) }} · {{ trans_choice('interface.configurations', count($summary['lines'])) }}</p>
            @foreach($summary['lines'] as $line)
                <div class="request-summary-line"><x-product-image :product="$products->get($line['configuration']['product_id'])" /><div><strong>{{ $line['name'] }}</strong><small>{{ $line['configuration']['length_cm'] }} × {{ $line['configuration']['width_cm'] }} cm · {{ trans_choice('interface.units', $line['configuration']['quantity']) }}</small>@if($line['configuration']['kind']==='special')<small>{{ $line['configuration']['material_description'] }}</small>@endif</div></div>
            @endforeach
            <x-quote-amount :amount="$summary['known_subtotal_clp']" :pending="$summary['pending']" />
            <p class="summary-footnote">Los importes se recalcularán al enviar. El transporte solicitado se confirma por separado.</p>
            <a class="button secondary" href="{{ route('cart.index') }}">Volver al carrito</a>
        </aside>
    </div>
</x-layout>
