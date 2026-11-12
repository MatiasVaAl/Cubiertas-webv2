# Preparación para producción

1. PHP compatible 8.3–8.5 con BCMath, PDO PostgreSQL, Fileinfo y extensiones Laravel; PostgreSQL soportado. Node solo para compilar.
2. `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`.
3. Configurar entorno privado, `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, clave estable y credenciales PostgreSQL restringidas. Mantener clave respaldada: cifra datos bancarios.
4. Respaldar base y almacenamiento privado, luego `php artisan migrate --force`. No `migrate:fresh`. Probar restauración.
5. Crear primer administrador mediante comando interactivo; sin usuarios/contraseñas predeterminados. No ejecutar seed demostrativo.
6. Document root exclusivamente `public/`; permisos de escritura en `storage` y `bootstrap/cache`. Bloquear archivos ocultos y directorios internos.
7. HTTPS, cookies seguras, sesiones cifradas, expiración razonable, rate limiting. No registrar query strings del intercambio de seguimiento ni cuerpos con credenciales/datos personales. Referrer-Policy no-referrer en seguimiento.
8. Almacenar comprobantes fuera de `public`, sin symlink. Descarga solo autenticada o autorizada por seguimiento; nunca servir archivos con ejecución activa.
9. Confirmar catálogo/disponibilidad, fórmula, redondeo/impuestos, tarifas, datos bancarios/contacto, plazos y políticas con el taller antes de habilitar venta automática.
10. `php artisan optimize`, monitorizar `/up`, errores sin datos sensibles, espacio de disco y copias. Verificar suite en entorno dedicado y flujos manuales/móvil antes de apertura.

No se requiere cola externa, Redis ni integraciones para MVP. Si se introducen notificaciones asíncronas, documentar workers y reintentos. El seguimiento privado es el canal inicial; no se promete correo/WhatsApp automático.
