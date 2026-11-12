# Pruebas y registro de etapas

## Estrategia
PHPUnit 12; unitarias para aritmética/canonicalización y funcionales PostgreSQL para constraints, persistencia, permisos y transiciones. Factories solo de desarrollo/pruebas. No usar SQLite para simular restricciones PostgreSQL.

Tests deben apuntar a una base dedicada cuyo nombre termine en `_test`; se utiliza migración incremental y transacciones. No ejecutar contra bases con datos de usuario. Configurar `.env.testing` desde `.env.testing.example`. Ver `README.md`.

## Registro
- Etapa 0: inspección completa; directorio vacío; documentación oficial consultada; arquitectura y ERD previos a migraciones de dominio.
- Laravel 13 instalado desde esqueleto oficial con `--no-scripts`: no se migró ninguna base existente.
- Se inicializó un clúster PostgreSQL 18 independiente en un directorio temporal, exclusivamente loopback/puerto 55438, rol de pruebas y base `cubiertas_temuco_test`. No se conectó a bases preexistentes del usuario. Las ocho migraciones se ejecutaron incrementalmente en cinco bloques.
- Etapas 1–7 implementadas y verificadas técnicamente; balance de archivos/resultados por etapa en `etapas.md`. Puesta en marcha de desarrollo y validación comercial pendientes.

## Resultados finales (12-11-2026)

| Comando / comprobación | Resultado observado |
|---|---|
| `php artisan migrate --env=testing --no-interaction` | Ocho migraciones aplicadas sin limpiar datos; constraints/triggers creados |
| `php artisan migrate:status --env=testing` | Las ocho migraciones en estado Ran |
| `php artisan test` | **36 pruebas, 209 aserciones, todas correctas**; última ejecución 4,20 s |
| `php vendor/laravel/pint/builds/pint --test` | Correcto después de aplicar formato |
| `npm run build` | Vite 7.3.8: compilación correcta de CSS/JS y manifest |
| `composer validate --strict` | composer.json válido y lock coherente |
| `composer audit` | Sin vulnerabilidades reportadas |
| `npm audit --omit=dev` | Sin vulnerabilidades reportadas; instalación inicial también reportó cero |
| `php artisan route:list --except-vendor` | 47 rutas de aplicación registradas |
| `php artisan view:cache --env=testing` | Plantillas Blade compiladas correctamente |
| `node scripts/browser-smoke.mjs` con Edge 153 | 12 comprobaciones de páginas (6 × 2 anchos), sin desborde horizontal ni errores JS; flujo reactivo especial→carrito→solicitud persistida→enlace privado→seguimiento correcto |
| Inspección de capturas | Inicio escritorio/móvil y configurador móvil revisados visualmente; formularios y navegación adaptados |
| `git check-ignore` | `.env`, `.env.testing`, vendor, node_modules y build excluidos; rama main inicializada sin commits |

Capturas y `results.json` del navegador quedaron en el directorio temporal `opencode/cubiertas-v2-browser`, fuera del repositorio. El script detiene sus procesos de PHP y navegador. El clúster temporal se detiene al terminar la sesión; para repetir integración hay que iniciarlo de nuevo o configurar otra base dedicada y ajustar `.env.testing`.

### Correspondencia de pruebas
- `AreaPriceTest` / `ClpTest`: superficie exacta, redondeo y dinero entero grande.
- `PricingTest`: fórmula apagada, límites semiabiertos, precio del servidor, cambios de tarifa, dimensiones/cantidad inválidas y exclusión de rangos en PostgreSQL.
- `CartQuoteTest`: agrupación, diferencias, cantidades, separar/eliminar, especiales, transporte pendiente, versiones/aceptación/idempotencia, precio manipulado y aislamiento de seguimiento.
- `CommercialIntegrityTest`: emisión estándar tras recálculo, adicionales confirmados/manuales, límites/disponibilidad, Livewire y edición, referencia privada, revisión de aceptación impaga, triggers inmutables, credenciales caducadas/ajenas, rate limit y desbordamiento monetario.
- `PaymentOrderTest`: retiro completo, fabricación prohibida sin verificar, permiso explícito, aprobación repetida idempotente, observación/nuevo archivo, reutilización, archivos falsos/grandes/privados, banco incompleto, cambios con comprobantes/pago bloqueados, PDF válido, espera/reanudación y transporte con evidencia.
- `AdministrationTest` / `AuthenticationTest`: acceso del equipo, cuentas desactivadas, pantallas y mantenimiento, roles, último administrador, contraseña sin predeterminados, cifrado y auditoría sin valores sensibles, rotación/revocación.

## Fallos encontrados y resueltos
- PowerShell interpretó inicialmente `^4.0` como versión exacta al pasar por Composer; la resolución segura quedó en composer.json y se instaló Livewire 4.4.8. No se ignoraron avisos de seguridad.
- Valores por defecto de PostgreSQL no aparecen automáticamente en modelos Eloquent recién creados; se hicieron explícitos los estados y permisos iniciales en los modelos.
- Se corrigió una directiva Blade sin separación de texto y se verificó el renderizado de las pantallas.
- Se desactivó el endpoint genérico de archivos del disco privado; solo se permite descarga por policy.
- Un intento de pruebas sufrió falta de memoria virtual del sistema Windows; las ejecuciones posteriores, incluida la suite final con `php artisan test`, terminaron normalmente.
- Se ajustó el middleware de headers para respuestas Symfony de descarga, además de respuestas HTML.
- El smoke de navegador requirió corregir su directorio de trabajo y escape de selector CDP. La aplicación y el flujo quedaron comprobados en la ejecución final.

## Cobertura requerida
Precios: área, rangos y límites, falta de tarifa, entradas inválidas, cambio de tarifa e histórico.
Carrito: agrupación/segregación, cantidades, eliminar, separar unidad, recálculo servidor.
Cotización: estándar/especial/transporte, versiones, aceptación vigente, pendientes y precio manipulado.
Pago: MIME/extensión/tamaño, archivo privado, autorización, observación, duplicación/idempotencia.
Fabricación: sin pago, estados inválidos, pedido entero, entrega y evidencia.
Seguridad: login, roles, rate limits, credenciales inválidas/ajenas, acceso privado y datos personales.

## Límites de esta verificación
- En la entrega inicial no se validó la conexión de desarrollo. Posteriormente el usuario la configuró y se verificó para cargar datos ficticios, según la ampliación siguiente. Las pruebas automatizadas nunca se ejecutan contra desarrollo.
- No se probaron tarifas ni ingresos bancarios reales; no hay información comercial proporcionada para ello.
- El navegador automatizado cubre páginas públicas y seguimiento; operaciones administrativas/pagos están cubiertos por HTTP/servicios en PHPUnit, sin recorrido visual completo del panel con datos abundantes.
- No se ejecutó auditoría de accesibilidad con lector de pantalla, ensayo de carga/concurrencia multiproceso, restauración de copias ni despliegue HTTPS. Las pruebas comprueban transacciones, constraints e idempotencia, no sustituyen un ensayo de carga real.
- Validar los escenarios con el taller y sus datos antes de declarar preparación productiva.

## Ampliación: datos para pruebas manuales

- `DemonstrationSeeder` ampliado a 10 acabados; `FunctionalDemoSeeder` agrega 14 solicitudes, 9 pedidos, 8 PDF sintéticos y 3 usuarios con contraseñas aleatorias en informe privado. No modifica fórmula, tarifas, banco ni otros datos del taller.
- `FunctionalDemoSeederTest`: **4 pruebas y 124 aserciones correctas** en PostgreSQL dedicado. Verifica consistencia, acceso, agrupación, límites, sesión/configuración intactas, repetición sin duplicar ni reiniciar, rechazo de producción y rollback con eliminación de archivos nuevos ante fallo.
- Suite completa tras el cambio: **40 pruebas y 333 aserciones correctas** (`php artisan test`, 5,55 s). Pint correcto. No se modificaron recursos frontend.
- Al comprobar desarrollo, `php artisan migrate:status --no-interaction` encontró las ocho migraciones ya aplicadas. Se ejecutó el seeder solicitado: CT-000001 a CT-000014; el comando terminó correctamente. No se borraron registros ni se instalaron cuentas bancarias ficticias.
- Detalle de escenarios y forma de uso en `docs/datos-prueba.md`.

## Avance independiente del rediseño frontend

- Referencias visuales solicitadas; el usuario indicó que adjuntará las imágenes. El rediseño de tres columnas todavía no está implementado.
- Se incorporaron traducciones españolas de las reglas actuales de servidor, paginación/autenticación, plurales y presentación reutilizable de importes pendientes/conocidos.
- `PublicFeedbackTest` verifica mensajes de endpoints, rechazo de contraseña sin números, singular/plural y resumen comercial no definitivo. Suite completa: **44 pruebas y 360 aserciones correctas**, 6,18 s.
- Pint y Vite correctos. Smoke de Edge ampliado a 390/768/1440: **18 comprobaciones de páginas**, sin desborde de documento ni errores JavaScript reportados; flujo especial→carrito→solicitud→seguimiento correcto.
- Sin cambios a servicios de negocio, modelos, migraciones ni datos de desarrollo. Alcance y fases pendientes en `docs/rediseno-frontend.md`.

## Rediseño integrado tras recibir las referencias

- Implementados cotizador visual de tres columnas, resumen reactivo, conversión decimal metros/centímetros, contador Livewire, carrito comercial, solicitud condicional, catálogo y navegación pública coherentes.
- Contactos sociales/mapa opcionales en el JSON de configuración existente, con validación HTTPS; sin migración ni información inventada.
- Suite final: **51 pruebas, 413 aserciones correctas** (`php artisan test`, 7,14 s). `VisualConfiguratorTest` verifica conversión, cambio de unidad, recálculo, agrupación sin redirección, contador, cantidades, indisponibilidad, separación/edición, referencia privada y enlaces de contacto. `CoverMeasurementsTest` comprueba geometría exacta y entradas inválidas.
- Las expectativas antiguas de redirección al agregar y etiqueta de precio se adaptaron al nuevo flujo solicitado, manteniendo las aserciones comerciales. Guardar una edición sigue redirigiendo al carrito.
- Pint y Vite correctos. Smoke general: **18 comprobaciones**, 390/768/1440. Recorrido profundo: **44 comprobaciones correctas**, mismas resoluciones, sin excepciones JavaScript reportadas.
- Recorrido profundo incluye escritura seguida inmediatamente de cambio de unidad, precio anterior oculto, controles del carrito con respuesta del servidor, agrupación, separación/edición/eliminación, pendientes/mixtos, solicitud/transporte/seguimiento y panel administrativo.
- Todos los cambios de datos para verificar se confinaron a PostgreSQL dedicado `_test`. Desarrollo no recibió escrituras, seeders ni migraciones. Sin modificaciones en servicios comerciales ni modelos.
- Capturas, comandos, componentes y limitaciones en `docs/rediseno-frontend.md`.
