<x-layout title="Operaciones · Cubiertas Temuco">
    <x-admin-nav />
    <h1>{{ ['quotes'=>'Cotizaciones','orders'=>'Pedidos','payments'=>'Pagos','deliveries'=>'Entregas'][$area] }}</h1>
    <form method="GET" class="card inline-form section">
        <label>Buscar<input name="q" value="{{ request('q') }}" placeholder="Número, nombre o teléfono"></label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                @foreach(match($area){'quotes'=>['pending_review','in_review','awaiting_information','awaiting_acceptance','accepted','rejected'],'payments'=>['pending','under_review','paid','observed'],'deliveries'=>['pending_pickup','ready_pickup','delivered','pending_dispatch','handed_to_carrier','delivery_confirmed'],default=>['pending','in_progress','completed']} as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ \App\Support\StatusLabel::for($status) }}</option>
                @endforeach
            </select>
        </label>
        <label>Orden<select name="sort"><option value="newest">Más recientes</option><option value="oldest" @selected(request('sort')==='oldest')>Más antiguas</option></select></label>
        <button class="button secondary">Aplicar filtros</button>
    </form>
    <div class="card table-wrap">
        <table>
            <thead><tr><th>Solicitud</th><th>Cliente</th><th>Cotización</th><th>Pago / fabricación / entrega</th><th>Acción</th></tr></thead>
            <tbody>
                @forelse($quotes as $quote)
                    <tr>
                        <td>{{ $quote->number }}<p class="muted">{{ $quote->created_at->format('d/m/Y') }}</p></td>
                        <td>{{ $quote->customer->name }}<p>{{ $quote->customer->phone }}</p></td>
                        <td><span class="badge">{{ \App\Support\StatusLabel::for($quote->status) }}</span></td>
                        <td>
                            @if($quote->order)
                                <p>{{ \App\Support\StatusLabel::for($quote->order->payment_status) }}</p>
                                <p>{{ \App\Support\StatusLabel::for($quote->order->manufacturing_status) }}</p>
                                <p>{{ \App\Support\StatusLabel::for($quote->order->delivery_status) }}</p>
                            @else
                                Sin pedido aceptado
                            @endif
                        </td>
                        <td><a href="{{ route('admin.quotes.show',$quote) }}">Abrir expediente →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay registros para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="section">{{ $quotes->links() }}</div>
</x-layout>
