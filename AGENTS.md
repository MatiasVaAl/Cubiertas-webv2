# Cubiertas Temuco Digital

## Forma de trabajo
- Aplicación monolítica Laravel 13 / PHP 8.3–8.5, PostgreSQL, Blade, Livewire 4 y Tailwind 4. No agregar SPA ni API separada.
- Leer `docs/arquitectura.md`, `docs/reglas-negocio.md` y `docs/pendientes.md` antes de modificar lógica comercial.
- Trabajar por etapas pequeñas verificables. Informar pruebas ejecutadas y limitaciones reales.
- Usar servicios para reglas, validaciones del servidor, policies y middleware para autorización.
- Nunca inventar precios, disponibilidad, datos bancarios o información del taller. No activar cálculo automático sin confirmación comercial explícita.
- Dinero CLP entero; dimensiones decimales normalizadas; cálculo exacto con BCMath. Nunca confiar en precios del navegador.
- Cotizaciones emitidas son versiones inmutables. Bloquear filas y usar transacciones para aceptación, pagos y estados.
- Fabricación por pedido completo. Pago total verificado obligatorio antes de iniciar. Sin entregas parciales.
- Tokens públicos solo como hash en base de datos; comprobantes privados. No escribir secretos en logs ni documentación.
- No ejecutar `migrate:fresh`, borrar datos existentes, crear commits o instalar herramientas globales sin indicación del usuario.
- Pruebas de integración exclusivamente en PostgreSQL dedicado terminado en `_test`, nunca en la base de desarrollo.
- Comandos: `php artisan test`, `vendor/bin/pint --test`, `npm run build`. En Windows usar `php vendor/laravel/pint/builds/pint --test` si es necesario.
- Mantener documentación y criterios de aceptación junto con cada módulo. No declarar una etapa completa sin su verificación.
