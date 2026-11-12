@props(['amount', 'pending' => false, 'label' => 'Subtotal de cubiertas'])

<div class="quote-amount">
    @if($pending && (int) $amount === 0)
        <h3>Total definitivo pendiente de confirmación</h3>
        <p>La solicitud requiere revisión del taller antes de confirmar su precio.</p>
        <p class="muted">Aún no hay importes confirmados.</p>
    @else
        <h3>{{ $pending ? 'Subtotal conocido' : $label }}: ${{ number_format($amount, 0, ',', '.') }} CLP</h3>
        @if($pending)
            <p>Hay valores pendientes. Este importe no es el total definitivo y no habilita pago.</p>
        @endif
    @endif
</div>
