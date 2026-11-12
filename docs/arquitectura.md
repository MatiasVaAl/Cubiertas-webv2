# Arquitectura y diagnóstico inicial

## Inspección (12-11-2026)
Directorio inicialmente vacío, sin `.git`, código ni instrucciones existentes. PHP 8.5.12 con PDO PostgreSQL y BCMath; Composer 2.10.3; Node 26.12.1; npm 11.20.0; Laravel Installer 5.32.0. PostgreSQL 18 como servicio activo, binarios en `D:\Apps\PostgresSQL\bin`; no está en PATH. Docker no disponible. Las credenciales locales serán configuradas por el usuario; no acceder a bases preexistentes.

## Compatibilidad consultada
- https://laravel.com/for/agents
- https://laravel.com/docs/13.x/releases (PHP 8.3–8.5; soporte seguridad hasta marzo de 2028).
- https://laravel.com/docs/13.x/deployment
- https://livewire.laravel.com/docs/4.x/installation (Laravel >=10; PHP >=8.1; resolución definitiva por Composer).

Se elige Laravel 13 estable. Se instala el esqueleto oficial con Composer sin scripts de migración automática, para documentar el ERD antes de migrar y evitar SQLite. Livewire se incorpora directamente: permite una interfaz Blade pequeña sin dependencias de un kit visual. No se requieren servicios Cloud, herramientas globales ni Boost para ejecutar el negocio.

## Módulos y límites
Una aplicación y una base PostgreSQL. `Models` representa persistencia, `Services` concentra cálculo y transiciones, `Http/Requests` valida entrada, `Policies` autoriza y `Http/Controllers` coordina. Blade sirve páginas; Livewire aporta interacción donde sea útil. La autorización debe repetirse en cualquier acción reactiva.

1. Identidad: sesiones Laravel, sin registro público; administrador/trabajador activo y permiso explícito para pagos.
2. Catálogo: acabados, fotografías y adicionales; no gestión compleja de materias primas.
3. Precios: interfaz reemplazable; estrategia provisional m² desactivada por defecto. Rangos `[mínimo, máximo)`; sin máximos comerciales inventados.
4. Carrito: configuraciones normalizadas en sesión, agrupación determinista, precios recalculados.
5. Cotizaciones: cliente, líneas persistidas, revisión y versiones inmutables con snapshots. Una aceptación crea un único pedido vigente; revisiones previas a comprobantes conservan el pedido anterior bloqueado.
6. Operaciones: pago completo, fabricación general y entrega con estados separados; transacciones y bloqueo pesimista.
7. Seguimiento: token aleatorio hash, intercambio por autorización de sesión, datos mínimos y archivos protegidos.

## Decisiones de persistencia
Ver `base-datos.md` y `diagrama-er.md`. Las líneas del pedido se obtienen de la versión aceptada: no duplicar tablas de detalles idénticas. Una entrega es parte del pedido; las condiciones aceptadas quedan en su snapshot. Los eventos comerciales son apéndices con autor y visibilidad pública; no se editan desde la interfaz.

## Orden y puertas de validación
0. Inspección, decisiones, ERD y plan documentados.
1. Bootstrap, conexión parametrizada, sesión, autenticación, permisos y assets; probar acceso público/administrativo y credenciales.
2. Migraciones incrementales, constraints PostgreSQL, factories y seeders seguros; migrar en base nueva de pruebas y verificar integridad.
3. Catálogo/configurador/precios; probar dimensiones, límites y ausencia de tarifas.
4. Carrito/solicitudes/versiones/aceptación; probar agrupación, manipulación, pendientes e inmutabilidad.
5. Pedido/transporte; comprobar total único y transporte manual pendiente.
6. Comprobantes privados/pagos; probar MIME, acceso y verificación autorizada/idempotente.
7. Dashboard/seguimiento/fabricación/entrega; probar transiciones y aislamiento entre clientes.
8. Suite PostgreSQL, formato, build y documentación. Revisión de navegador móvil debe reportarse separadamente si no hay navegador automatizable.

Cada puerta se registra en `docs/pruebas.md`. No afirmar ejecución sobre la conexión del usuario hasta que esté configurada. Para integración puede utilizarse un clúster PostgreSQL temporal aislado, escuchando únicamente en loopback y detenido al terminar.
