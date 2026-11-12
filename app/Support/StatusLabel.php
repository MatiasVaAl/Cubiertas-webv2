<?php

namespace App\Support;

class StatusLabel
{
    public static function for(string $status): string
    {
        return [
            'pending_review' => 'Pendiente de revisión', 'in_review' => 'En revisión', 'awaiting_information' => 'Esperando información', 'awaiting_acceptance' => 'Esperando aceptación', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada',
            'pending' => 'Pendiente', 'under_review' => 'En verificación', 'paid' => 'Pagado', 'observed' => 'Pago observado', 'in_progress' => 'En fabricación', 'completed' => 'Fabricación terminada',
            'pending_pickup' => 'Pendiente de retiro', 'ready_pickup' => 'Listo para retiro', 'delivered' => 'Entregado al cliente', 'pending_dispatch' => 'Pendiente de despacho', 'handed_to_carrier' => 'Entregado al transportista', 'delivery_confirmed' => 'Entrega confirmada',
        ][$status] ?? $status;
    }
}
