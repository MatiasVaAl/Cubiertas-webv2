# Requisitos y criterios de aceptación

## Funcionales
F01 catálogo y guía pública responsive; F02 configurador MDF/laminado, medidas y adicionales; F03 cotizador con tarifas confirmadas o revisión; F04 carrito anónimo agrupado con edición/separación; F05 solicitud única estándar/especial; F06 retiro/transporte manual; F07 revisión/versionado y aceptación privada; F08 transferencia íntegra y comprobantes privados; F09 verificación humana autorizada; F10 fabricación por pedido; F11 entrega sin parciales; F12 seguimiento sin registro; F13 administración con roles; F14 historial con responsable.

## No funcionales
PostgreSQL real, migraciones/restricciones, Eloquent, servicios transaccionales, frontend Blade/Livewire/Tailwind, CSRF, validación servidor, control de permisos, rate limiting, archivos privados, no secretos en logs, snapshots históricos, pruebas automatizadas, navegación accesible y móvil. Sin WhatsApp, SPA, pasarelas ni integraciones externas en MVP.

## Aceptación verificable
1. Medidas válidas + tarifa confirmada + fórmula aprobada generan precio exacto.
2. Solicitud conserva varias configuraciones, medidas y cantidades.
3. Agregar configuración igual incrementa cantidad; distintas no se mezclan.
4. Especial no se emite hasta confirmar precio y factibilidad.
5. Transporte no muestra cero cuando está pendiente.
6. Propuesta completa versionada requiere aceptación de versión vigente.
7. Solo transferencia del total; archivo no confirma dinero.
8. Solo autorizado verifica; duplicados no producen dos pagos.
9. Sin pago verificado no comienza fabricación; estado único por pedido.
10. Token/código privado da acceso solo a la solicitud correspondiente.
11. Administrador mantiene catálogo, tarifas, usuarios y configuración.
12. Público no administra ni puede manipular precios ni acceder a comprobantes ajenos.

Estado real de implementación y ejecución en `pruebas.md`; esta lista define el objetivo, no afirma que esté completado.
