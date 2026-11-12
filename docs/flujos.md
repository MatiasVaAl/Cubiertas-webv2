# Flujos

## Cliente
Inicio → catálogo → configurador (o especial) → carrito con subtotal conocido y pendientes → contacto y retiro/transporte → solicitud persistida + credencial privada → revisión humana si corresponde → propuesta versionada → aceptación → instrucciones bancarias configuradas → transferencia total → comprobante privado → verificación humana → fabricación → retiro/despacho → entrega.

El enlace privado usa fragmento `#number=…&code=…`, que no se envía al servidor HTTP; JavaScript lo elimina del historial e intercambia por POST el secreto por una autorización en sesión. Redirige a una URL sin secreto. El formulario alternativo permite el mismo intercambio sin JavaScript y solicita número y código; el número por sí solo no autoriza. No registrar cuerpos de estos POST ni secretos en eventos/logs.

## Trabajador
Login → dashboard real → solicitud en pantalla única → revisar disponibilidad, precio manual con justificación y transporte → emitir versión completa → esperar aceptación/comprobante → autorizado verifica ingreso → iniciar fabricación → terminar → preparar retiro o entregar al transportista → confirmar entrega con evidencia.

## Administrador
Además de operaciones: catálogo, rangos confirmados, adicionales, límites, confirmación de fórmula, datos generales/bancarios y usuarios. Activar cálculo requiere confirmación comercial explícita. Desactivar un usuario invalida su autorización en siguientes peticiones.

## Recuperación
Personal autorizado verifica identidad por un procedimiento del taller y rota la credencial. Nunca entregar acceso solo porque alguien conozca el número consecutivo. No automatizar identidad usando información fácilmente adivinable.
