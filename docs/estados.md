# Estados y transiciones

Cotización: `pending_review` → `in_review` → `awaiting_information` / `awaiting_acceptance` / `rejected`. Una solicitud estándar completa puede pasar directamente a `awaiting_acceptance`. El cliente acepta únicamente la versión vigente: `awaiting_acceptance` → `accepted`. Solicitar cambio vuelve a revisión y deja sin autorización de pago. Rechazo es terminal. Una aceptación sin comprobantes y con pago pendiente admite `accepted` → `in_review`: el pedido anterior queda sustituido/bloqueado e inmutable; emitir otra versión requiere nueva aceptación. Con comprobantes/pagos no se permite.

Pago del pedido: `pending` → `under_review` → `paid` o `observed`; observado permite nuevo comprobante. Verificar requiere permiso, ingreso completo confirmado y transacción. Repetir aprobación del mismo comprobante aprobado es idempotente; no crear otro pago válido.

Fabricación del pedido: `pending` → `in_progress` → `completed`. Inicio requiere `paid`. No saltar etapas, retroceder ni administrar unidades individuales.

Retiro: `pending_pickup` → `ready_pickup` → `delivered`. Transporte: `pending_dispatch` → `handed_to_carrier` → `delivery_confirmed`. Preparar retiro/despachar requiere fabricación terminada; confirmar entrega de transporte requiere evidencia/observación. Entregado al transportista no es recibido por cliente.

En espera: bandera y observación separadas; no reescribe historial ni estados. Reanudar se registra. Bloquea avances mientras está activa.

Las operaciones críticas bloquean cotización/pedido dentro de transacciones. Los controles visuales no reemplazan estas reglas.
