# Modelo de datos (diseñado antes de migraciones de dominio)

PostgreSQL 18, claves bigint, timestamps, FK restrictivas para historial. Los importes son bigint CLP no negativos; dimensiones numeric(12,2). Límites técnicos de representación no representan capacidad del taller.

| Entidad | Responsabilidad / restricciones |
|---|---|
| users | Trabajadores; email único, contraseña hash, rol admin/worker, activo, permiso verify_payments |
| products | Acabado MDF/laminado, código único, descripción/color/terminación/características, disponibilidad y demostrativo |
| product_images | Fotos ordenadas con texto alternativo; relación producto |
| width_rates | Producto, límites de ancho `[min,max)`, CLP/m², confirmación y actividad; exclusión GiST de solapamiento por producto activo |
| additional_works | Adicional habilitado, modalidad manual o por unidad con precio expresamente confirmado |
| workshop_settings | Registro único con datos editables, confirmación de fórmula, límites opcionales y datos bancarios cifrados |
| customers | Datos mínimos de contacto de cada solicitud; no identidad de acceso ni deduplicación por teléfono |
| quotes | Cliente, estado, modalidad, destino, observaciones, revisión actual; número visible derivado de PK |
| quote_lines | Configuración JSON canónica, cantidades, snapshot de precio y motivos pendientes; relaciones opcionales al catálogo |
| quote_versions | Número secuencial por cotización, snapshot completo, total, autor, fecha; inmutable |
| orders | Un pedido vigente por cotización + versión aceptada única, total congelado, estados independientes de pago/fabricación/entrega, datos de despacho/espera; pedidos sustituidos permanecen históricos |
| payments | Pedido, archivo privado, SHA-256 único, importe esperado, revisión, responsable y observaciones |
| tracking_credentials | Cotización, hash de token único, vencimiento y revocación; nunca texto claro |
| state_events | Cotización, pedido opcional, actor opcional, tipo, mensaje y visibilidad al cliente |
| operation_audits | Cambios administrativos con actor, entidad y nombres de campos; no copiar secretos ni valores bancarios |

## Normalización e historial
El catálogo es mutable; las líneas conservan código/nombre, dimensiones, adicionales y precios en JSON. Cada versión copia las líneas resueltas y las condiciones de transporte. El pedido referencia exactamente una versión aceptada: sus detalles no necesitan otra tabla redundante. Los estados de pago/fabricación/entrega pertenecen al pedido completo. Un evento no sustituye los estados actuales ni guarda secretos.

## Índices y concurrencia
Índices por estado/fecha para colas administrativas, FK indexadas, unicidad quote/version y pedido vigente por cotización (índice parcial `superseded_at IS NULL`), hash de credenciales y comprobantes. FK compuesta asegura que la versión del pedido pertenece a su cotización. Un solo pago verificado por pedido. Exclusión de rangos mediante `numrange` y expresión GiST del identificador de producto (sin extensión btree_gist). CHECK para cantidades, montos, anchos y estados válidos. Edición de tarifas y aceptación/versionado bloquean agregados. Los índices y constraints constituyen la última barrera ante carreras.

Si una aceptación se reabre antes de recibir comprobantes, su pedido se marca `superseded_at`, conserva el importe/versionado original y no admite pagos ni transiciones. Una nueva aceptación crea otro pedido vigente. No es cancelación comercial ni devolución. Después de recibir comprobantes este cambio se bloquea.

## Seguridad de migraciones
Solo `php artisan migrate`; nunca limpiar bases existentes. Tests con transacciones y migración incremental en una base nueva dedicada. El usuario configura su conexión local sin revelar credenciales en conversación.
