<div x-data x-on:cover-added.window="$nextTick(() => { $refs.added?.focus(); $refs.added?.scrollIntoView({block: 'nearest', behavior: 'smooth'}); })">
    @if($added)
        <div class="added-notice" role="status" tabindex="-1" x-ref="added">
            <x-icon name="check" /><div><strong>Configuración agregada al carrito</strong><p>Puedes seguir cotizando aquí o revisar todas tus cubiertas.</p></div>
            <a href="{{ route('cart.index') }}" class="button secondary">Ver carrito <x-icon name="arrow" /></a>
        </div>
    @endif
    <form wire:submit="addToCart" class="quote-workspace">
        <x-contact-help :contact="$contact" />
        <section class="config-main">
            <header class="shop-heading"><p class="eyebrow">HECHO PARA TU ESPACIO</p><h1>{{ $editKey ? 'Edita tu cubierta' : 'Cotiza tu cubierta a medida' }}</h1><p>Selecciona el acabado, ingresa las medidas y configura las cubiertas que necesitas.</p></header>
            <fieldset class="config-section">
                <legend class="section-title"><span>1</span> Elige el acabado</legend>
                <div class="kind-switch" role="group" aria-label="Tipo de cubierta">
                    <label class="{{ $kind === 'standard' ? 'active' : '' }}"><input type="radio" wire:model.live="kind" value="standard">Acabados del catálogo</label>
                    <label class="{{ $kind === 'special' ? 'active' : '' }}"><input type="radio" wire:model.live="kind" value="special">Material especial</label>
                </div>
                @if($kind === 'standard')
                    <div class="finish-options">
                        @forelse($visibleProducts as $product)
                            <x-finish-card :product="$product" selectable :selected="(string) $product->id === $product_id" />
                        @empty
                            <p class="empty-hint">Aún no hay acabados publicados. Selecciona «Material especial» para solicitar una evaluación.</p>
                        @endforelse
                    </div>
                    @if($products->count() > 6)<button class="finish-expand" type="button" wire:click="$toggle('showAllFinishes')" aria-expanded="{{ $showAllFinishes ? 'true' : 'false' }}">{{ $showAllFinishes ? 'Mostrar menos acabados' : 'Ver todos los acabados ('.$products->count().')' }} <x-icon name="{{ $showAllFinishes ? 'minus' : 'plus' }}" /></button>@endif
                    <x-field-error name="product_id" />
                @else
                    <div class="form-stack">
                        <p class="review-hint"><x-icon name="info" /> El taller evaluará disponibilidad, factibilidad y precio.</p>
                        <label for="material-name">Material solicitado<input id="material-name" wire:model.live.debounce.350ms="material_name" maxlength="255" aria-describedby="material_name-error" required></label>
                        <x-field-error name="material_name" />
                        <label for="material-description">Descripción del material<textarea id="material-description" wire:model.live.debounce.350ms="material_description" maxlength="3000" placeholder="Describe el acabado o las características que buscas."></textarea></label>
                        <x-field-error name="material_description" />
                    </div>
                @endif
            </fieldset>

            <fieldset class="config-section">
                <legend class="section-title"><span>2</span> Ingresa las medidas</legend>
                <div class="measure-grid">
                    <div>
                        <label for="cover-length">Largo de la cubierta</label>
                        <div class="measure-control">
                            <input id="cover-length" wire:model.live.debounce.350ms="lengthInput" inputmode="decimal" placeholder="{{ $lengthUnit === 'm' ? 'Ej. 2,40' : 'Ej. 240' }}" aria-invalid="{{ $errors->has('length_cm') ? 'true' : 'false' }}" aria-describedby="length-help length_cm-error" required>
                            <select wire:change="changeLengthUnit($event.target.value)" aria-label="Unidad del largo"><option value="m" @selected($lengthUnit === 'm')>m</option><option value="cm" @selected($lengthUnit === 'cm')>cm</option></select>
                            <button type="button" wire:click="adjustMeasure('length_cm', -1)" aria-label="Disminuir largo en 10 centímetros"><x-icon name="minus" /></button>
                            <button type="button" wire:click="adjustMeasure('length_cm', 1)" aria-label="Aumentar largo en 10 centímetros"><x-icon name="plus" /></button>
                        </div>
                        <small id="length-help">{{ $lengthUnit === 'm' ? 'Metros: 2,40 m equivalen a 240 cm.' : 'Centímetros. Los botones ajustan de 10 en 10.' }}</small>
                        <x-field-error name="length_cm" />
                    </div>
                    <div>
                        <label for="cover-width">Ancho de la cubierta</label>
                        <div class="measure-control">
                            <input id="cover-width" wire:model.live.debounce.350ms="width_cm" inputmode="decimal" placeholder="Ej. 60" aria-invalid="{{ $errors->has('width_cm') ? 'true' : 'false' }}" aria-describedby="width-help width_cm-error" required><span>cm</span>
                            <button type="button" wire:click="adjustMeasure('width_cm', -1)" aria-label="Disminuir ancho en un centímetro"><x-icon name="minus" /></button>
                            <button type="button" wire:click="adjustMeasure('width_cm', 1)" aria-label="Aumentar ancho en un centímetro"><x-icon name="plus" /></button>
                        </div>
                        <small id="width-help">Profundidad de la cubierta, en centímetros.</small>
                        <x-field-error name="width_cm" />
                    </div>
                    <div class="quantity-field"><label for="cover-quantity">Cantidad de cubiertas iguales</label><x-quantity-stepper :value="$quantity" livewire id="cover-quantity" /><x-field-error name="quantity" /></div>
                </div>
            </fieldset>

            <fieldset class="config-section">
                <legend class="section-title"><span>3</span> Detalles adicionales <small>(opcional)</small></legend>
                @if($works->isNotEmpty())
                    <div class="work-options">
                        @foreach($works as $work)
                            <label class="work-option" wire:key="work-{{ $work->id }}"><input type="checkbox" wire:model.live="additional_ids" value="{{ $work->id }}"><span><strong>{{ $work->name }}</strong><small>{{ $work->confirmed && $work->pricing_mode === 'per_unit' ? 'Precio confirmado por cubierta' : 'Precio sujeto a revisión' }}</small></span></label>
                        @endforeach
                    </div>
                    <x-field-error name="additional_ids" />
                @else
                    <p class="muted">No hay trabajos adicionales publicados. Describe cualquier necesidad en las observaciones para que el taller la evalúe.</p>
                @endif
                <label for="cover-notes">Observaciones de fabricación<textarea id="cover-notes" wire:model.live.debounce.500ms="notes" maxlength="3000" placeholder="Cuéntanos los detalles que el taller debe revisar."></textarea></label>
                <x-field-error name="notes" />
                @if($kind === 'special')
                    <label class="upload-field" for="cover-reference"><x-icon name="image" /><span>Imagen de referencia opcional<small>JPG, PNG o WebP · máximo 5 MB</small></span><input id="cover-reference" type="file" wire:model="reference" accept="image/jpeg,image/png,image/webp"></label>
                    <p wire:loading wire:target="reference" role="status">Cargando imagen…</p>
                    @if($reference instanceof \Illuminate\Http\UploadedFile)<p class="muted">Imagen seleccionada: {{ $reference->getClientOriginalName() }}</p>@elseif($referencePath)<p class="muted">Tu imagen de referencia está guardada y se conservará.</p>@endif
                    <x-field-error name="reference" />
                @endif
            </fieldset>
        </section>

        <aside class="quote-sidebar">
            <section class="quote-summary">
                <h2>Tu cotización</h2>
                <div class="summary-product"><x-product-image :product="$selectedProduct" /><div><strong>{{ $kind === 'special' ? ($material_name ?: 'Material especial') : ($selectedProduct?->name ?? 'Selecciona un acabado') }}</strong><small>{{ $kind === 'special' ? 'Evaluación personalizada' : ($selectedProduct?->code ?? 'MDF + laminado decorativo') }}</small></div></div>
                <dl class="summary-facts">
                    <div><dt>Largo</dt><dd>{{ $length_cm !== '' ? \App\Support\CoverMeasurements::display(str_replace(',', '.', $length_cm)) : '—' }} cm</dd></div>
                    <div><dt>Ancho</dt><dd>{{ $width_cm !== '' ? $width_cm : '—' }} cm</dd></div>
                    <div><dt>Cantidad</dt><dd>{{ $quantity }}</dd></div>
                    <div><dt>Superficie por unidad</dt><dd>{{ \App\Support\CoverMeasurements::display($surface) }} m²</dd></div>
                </dl>
                @if($additional_ids)<div class="summary-extras"><strong>Adicionales</strong>@foreach($works->whereIn('id', $additional_ids) as $work)<span>{{ $work->name }}</span>@endforeach</div>@endif
                <div class="summary-price" wire:dirty.remove>
                    <div wire:loading.remove>
                        @if($preview && !$preview['pending_reasons'])
                            <dl class="summary-facts"><div><dt>Precio unitario</dt><dd>${{ number_format($preview['unit_price_clp'], 0, ',', '.') }}</dd></div></dl>
                            <p class="summary-total"><span>Subtotal</span><strong>${{ number_format($preview['subtotal_clp'], 0, ',', '.') }} <small>CLP</small></strong></p>
                        @elseif($preview)
                            <p class="price-pending">Precio por confirmar</p>
                            <p class="review-hint"><x-icon name="info" /> Esta configuración requiere revisión del taller antes de confirmar su precio.</p>
                            @if($preview['known_subtotal_clp'] > 0)<p class="muted">Subtotal conocido: ${{ number_format($preview['known_subtotal_clp'], 0, ',', '.') }} CLP. No es el total definitivo.</p>@endif
                            <details class="pending-details"><summary>¿Qué requiere revisión?</summary>@foreach($preview['pending_reasons'] as $reason)<p>{{ $reason }}</p>@endforeach</details>
                        @else
                            <p class="price-pending">Completa tu configuración</p><p class="muted">Selecciona un acabado o material e indica medidas positivas para consultar su precio.</p>
                        @endif
                    </div>
                </div>
                <p class="summary-updating" wire:dirty role="status">Actualizando tu configuración…</p>
                <p wire:loading role="status">Actualizando tu cotización…</p>
                @if($errors->any())<div class="errors compact-errors" role="alert"><strong>Revisa los datos de tu cubierta.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <button class="button summary-submit" type="submit" wire:loading.attr="disabled"><x-icon name="{{ $editKey ? 'check' : 'cart' }}" />{{ $editKey ? 'Guardar cambios' : 'Agregar al carrito' }}</button>
                <a class="button secondary" href="{{ route('cart.index') }}">Ver carrito</a>
                <p class="summary-footnote">El transporte se confirma según tu solicitud. No se requiere pagar en este paso.</p>
            </section>
        </aside>
    </form>
</div>
