# Cubiertas Temuco Digital

Monolito Laravel para cotizar cubiertas MDF/laminado a medida, reunir configuraciones en un carrito y gestionar revisión, aceptación, transferencia íntegra, fabricación y entrega. Clientes sin registro y equipo con acceso por roles.

**No contiene tarifas, inventario, contacto ni cuentas bancarias reales.** El cálculo automático permanece desactivado hasta confirmar fórmula y tarifas. Los datos ficticios solo se usan en pruebas o mediante un seeder demostrativo explícito.

## Entorno

- PHP 8.3–8.5 con BCMath, PDO PostgreSQL, Fileinfo, GD y extensiones requeridas por Laravel.
- Composer 2; PostgreSQL (verificado con 18); Node compatible con Vite 7 (20.19+/22.12+), npm.
- Dependencias bloqueadas: Laravel 13.42.0, Livewire 4.4.8, Tailwind 4, PHPUnit 12.
- No requiere Docker, Redis, una SPA ni servicios externos.

## Instalación y ejecución

1. Instalar dependencias desde los lockfiles:

   ```powershell
   composer install
   npm ci
   ```

2. Solo en una instalación nueva, copiar `.env.example` como `.env`; si ya existe, conservarlo. Configurar `APP_URL` y PostgreSQL:

   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=cubiertas_temuco
   DB_USERNAME=cubiertas
   DB_PASSWORD=
   ```

   Crear previamente una base vacía y un rol propios. Introducir la contraseña únicamente en `.env`, que está excluido de Git. No usar bases de otros proyectos. En este entorno PostgreSQL está en `D:\Apps\PostgresSQL\bin`, fuera de PATH.

3. Generar la clave **solo si está vacía** y ejecutar migraciones incrementales:

   ```powershell
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   php artisan cubiertas:create-admin
   npm run build
   ```

   El comando de administrador solicita una contraseña privada de al menos 12 caracteres, con letras y números. No existe registro público ni una cuenta predeterminada. `storage:link` publica únicamente fotografías del catálogo; comprobantes y referencias están en `storage/app/private`.

4. Iniciar:

   ```powershell
   composer run dev
   ```

   Abrir http://localhost:8000. Acceso del equipo: http://localhost:8000/equipo/ingresar. Alternativamente, usar `php artisan serve` y `npm run dev` en terminales separadas.

**Estado local:** la conexión PostgreSQL de desarrollo ya fue comprobada y sus migraciones están aplicadas. Se cargaron los datos ficticios de `FunctionalDemoSeeder` a petición del usuario. La suite automatizada se ejecuta en un clúster de pruebas independiente, nunca en la base de desarrollo.

## Primer uso

1. Ingresar como administrador.
2. Configurar datos reales del taller y datos bancarios completos.
3. Publicar acabados con disponibilidad confirmada y fotografías autorizadas.
4. Confirmar fórmula/redondeo/tratamiento tributario con el taller; después habilitarla en Configuración y crear rangos/tarifas confirmadas.
5. Configurar adicionales y límites únicamente si se conocen. Todo lo no tarifado pasa a revisión.
6. Crear trabajadores y marcar individualmente quién puede verificar pagos.

Para explorar la interfaz **solo en local/testing**, opcionalmente:

```powershell
php artisan db:seed --class=DemonstrationSeeder
```

Crea 10 acabados identificados como demostrativos (8 habilitados y 2 no disponibles), sin precios automáticos. No crea usuarios, tarifas ni datos bancarios.

Para disponer además de **14 solicitudes con clientes, pedidos, pagos y estados variados**, ejecutar:

```powershell
php artisan db:seed --class=FunctionalDemoSeeder
```

Crea 3 usuarios de demostración y guarda sus contraseñas aleatorias y los enlaces de seguimiento en el archivo privado que indica el comando. La carga es repetible: no duplica escenarios ni restablece su avance. No cambia tarifas, fórmula ni datos bancarios. Ver [datos de prueba y escenarios](docs/datos-prueba.md).

`DatabaseSeeder` no inserta datos ficticios por defecto. Estos seeders solo se permiten en local/testing.

## Pruebas

Crear una base PostgreSQL dedicada cuyo nombre termine en `_test`. Copiar `.env.testing.example` como `.env.testing`, configurar sus credenciales y generar una clave propia si está vacía:

```powershell
php artisan key:generate --env=testing
php artisan migrate --env=testing
php artisan test
php vendor/laravel/pint/builds/pint --test
npm run build
```

Las pruebas funcionales comprueban tanto configuración como `current_database()` **antes** de activar sus transacciones. No usan `migrate:fresh`, `RefreshDatabase` ni SQLite. Las migraciones se ejecutan una vez explícitamente; las pruebas revierten sus propios cambios mediante transacciones.

`php artisan test --testsuite=Unit` ejecuta solo aritmética sin conexión. Para pruebas funcionales se necesita la base PostgreSQL dedicada disponible. No ejecutar suites simultáneas sobre el mismo almacenamiento falso de Laravel.

### Navegador real

`scripts/browser-smoke.mjs` usa Node y Edge/Chromium sin instalar paquetes adicionales. Requiere base de pruebas migrada, `.env.testing` y assets compilados. Levanta y detiene un PHP temporal en 8093 y navegador en puerto de depuración 9239; ambos puertos deben estar libres.

Configurar `BROWSER_BIN` con la ruta al ejecutable, `PHP_BIN` con el **php.exe** real y `BROWSER_ARTIFACTS` con un directorio temporal. Ejecutar:

```powershell
node scripts/browser-smoke.mjs
```

Comprueba seis páginas en anchos 390/768/1440, ausencia de desborde horizontal, Livewire → carrito → solicitud especial → enlace privado → seguimiento. Usa exclusivamente datos ficticios y deja esa solicitud en la base de pruebas. Capturas y resultados quedan en el directorio de artefactos, fuera del repositorio.

## Seguridad y decisiones

- Sesiones Laravel, CSRF, escape Blade, validación servidor y rate limits.
- Policies para cotizaciones, pedidos y comprobantes; middleware de personal activo y gate administrativo.
- CLP entero con BCMath; rangos `[mínimo, máximo)` y exclusión GiST PostgreSQL sin extensiones adicionales.
- Versiones e historial inmutables mediante triggers; importe y versión del pedido aceptado protegidos en base de datos.
- Transacciones y bloqueo de filas para aceptación, revisión, pagos y estados; unicidad de pedido vigente y pago verificado.
- Referencias/comprobantes privados; MIME real, extensión y tamaño; datos bancarios cifrados; no guardar secretos en auditoría.
- Código de 256 bits almacenado como hash. Enlaces con secreto en fragmento (no en la petición HTTP), intercambio por sesión de dos horas; credencial de 90 días configurable con `TRACKING_DAYS`. Rotación tras verificación de identidad revoca sesiones anteriores.
- Revisiones de una aceptación sin comprobantes conservan el pedido anterior como histórico bloqueado. Con comprobantes o pagos no se cambia el importe por este flujo.
- La confirmación de recepción bancaria es manual; adjuntar archivo no inicia fabricación.

## Documentación

| Documento | Contenido |
|---|---|
| [Requisitos](docs/requisitos.md) | Alcance y criterios verificables |
| [Arquitectura](docs/arquitectura.md) | Diagnóstico, dependencias y límites de módulos |
| [Base de datos](docs/base-datos.md) / [ERD](docs/diagrama-er.md) | Entidades, relaciones y decisiones de normalización |
| [Reglas](docs/reglas-negocio.md) / [Estados](docs/estados.md) | Condiciones y transiciones |
| [Flujos](docs/flujos.md) | Cliente, trabajador y recuperación |
| [Pendientes](docs/pendientes.md) | Datos y políticas comerciales no confirmados |
| [Pruebas](docs/pruebas.md) | Resultados y limitaciones reales |
| [Datos de prueba](docs/datos-prueba.md) | 10 acabados y 14 escenarios para probar la interfaz |
| [Análisis de vistas](docs/analisis-vistas.md) | Revisión completa de pantallas y mejoras priorizadas |
| [Rediseño frontend](docs/rediseno-frontend.md) | Cotizador y carrito visuales, componentes y resultados de verificación |
| [Recorrido visual](scripts/browser-shop.mjs) | Validación del cotizador/carrito con fixtures PostgreSQL dedicados |
| [Etapas](docs/etapas.md) | Archivos, funcionalidades, puertas verificadas y siguiente paso |
| [Despliegue](docs/despliegue.md) | Preparación para producción |

La validación comercial y la configuración de la base de desarrollo son necesarias antes de utilizar el sistema con información real.
