# Informe de desarrollo por etapas

Los módulos se incorporaron por bloques con pruebas entre ellos. Este informe distingue implementación verificada en PostgreSQL dedicado de puesta en marcha comercial/local aún pendiente.

## 0 — Inspección y diseño
- Archivos: `AGENTS.md`, `docs/{arquitectura,requisitos,reglas-negocio,base-datos,diagrama-er,pendientes,flujos,estados}.md`.
- Resultado: directorio inicialmente vacío y sin Git; herramientas/versiones detectadas; consulta oficial Laravel/Livewire; ERD previo a migraciones de dominio.
- Decisiones: monolito Laravel 13, Livewire 4, Blade/Tailwind; PostgreSQL y BCMath. Sin precios, datos bancarios ni disponibilidad inventados.
- Siguiente puerta: bootstrap y acceso del equipo.

## 1 — Base técnica y autenticación
- Archivos: `composer.{json,lock}`, `package*.json`, `.env*.example`, `bootstrap/app.php`, `config/{database,filesystems}.php`, `routes/web.php`, `AuthController`, `EnsureActiveStaff`, `User`, `CreateAdministrator`, layout y login.
- Implementado: sesiones, regeneración al ingresar/salir, acceso de personal activo, roles, permiso explícito de pagos; administrador interactivo sin clave predeterminada.
- Pruebas: `AuthenticationTest`; acceso público, bloqueo del panel, login y desactivación. Build Vite ejecutado.
- Limitación: base de desarrollo parametrizada, pendiente de credenciales del usuario. El PostgreSQL temporal de integración es independiente.
- Siguiente puerta: dominio PostgreSQL.

## 2 — Base de datos
- Archivos: migraciones `2026_11_12_000001` a `000005`, modelos de catálogo/cotizaciones/pedidos/pagos/seguimiento/auditoría; `ProductFactory`, `UserFactory`, seeders seguros.
- Implementado: FK, CHECK, índices, exclusión GiST de rangos, versiones/eventos/auditoría inmutables, total aceptado protegido, pedido vigente y pago válido únicos.
- Pruebas: migraciones incrementales contra base nueva `_test`, constraints de solapamiento y mutación de versiones/importes en `PricingTest` / `CommercialIntegrityTest`.
- Limitación: migración sobre la base de desarrollo pendiente; no se ejecutaron pruebas destructivas ni rollback sobre datos de usuario.
- Siguiente puerta: catálogo/cálculo.

## 3 — Catálogo y configurador
- Archivos: `CatalogController`, `HomeController`, `CoverConfigurator`, `ConfigurationService`, `Services/Pricing/*`, `Support/Clp.php`, vistas públicas, `resources/css/{app,site}.css`.
- Implementado: catálogo/detalle/fotos, guía, configurador reactivo, medidas normalizadas, límites opcionales, adicionales, cálculo por estrategia reemplazable, modo pendiente sin tarifa/fórmula y exclusión de demostrativos de producción.
- Pruebas: `AreaPriceTest`, `ClpTest`, `PricingTest`, `CommercialIntegrityTest`; preview y carrito también comprobados en navegador real.
- Provisional: m² y redondeo unitario mitad hacia arriba; fórmula apagada por defecto. No se precargaron tarifas comerciales.
- Siguiente puerta: solicitud única y propuesta.

## 4 — Carrito y cotizaciones
- Archivos: `CartService`, `CartController`, `QuoteService`, `QuoteController`, `SubmitQuoteRequest`, vistas de carrito/solicitud/líneas.
- Implementado: agrupación normalizada, distintas configuraciones separadas, edición, cantidades, eliminación, separación individual; especial con referencia privada; recálculo al enviar; persistencia, revisión, alternativas en resolución, rechazo, versiones y aceptación idempotente.
- Pruebas: `CartQuoteTest` / `CommercialIntegrityTest`; cambio de tarifa antes de envío, precio manipulado, pendientes, aceptación antigua, histórico y cambio previo a comprobantes.
- Siguiente puerta: consolidación de pedido y transporte.

## 5 — Pedidos y transporte
- Archivos: `Order`, `QuoteVersion`, `QuoteService`, migración de revisión impaga, vistas de expediente y seguimiento.
- Implementado: un pedido vigente con versión aceptada, retiro cero confirmado, transporte manual pendiente/confirmado, snapshot de condiciones; revisar aceptación impaga conserva pedido anterior bloqueado.
- Pruebas: versión vigente, unicidad/idempotencia, transporte de prueba, conservación de importes, prohibición de modificar con comprobantes/pagos.
- Siguiente puerta: comprobante y verificación.

## 6 — Transferencia íntegra
- Archivos: `PaymentService`, `PaymentController`, `PaymentPolicy`, `OrderPolicy`, `WorkshopSetting`, vistas de pago/verificación.
- Implementado: banco cifrado y datos completos antes de habilitar carga; JPG/PNG/WebP/PDF reales hasta 5 MB, integridad básica de imagen/PDF, almacenamiento privado, SHA-256 único, pago observado y nuevo comprobante, aprobación manual autorizada/idempotente y única por pedido.
- Pruebas: `PaymentOrderTest`; PDF válido, archivo manipulado/grande, acceso público/ajeno, falta de banco, comprobante reutilizado, permiso, confirmación bancaria y bloqueo previo a fabricación.
- Límite: validación de PDF comprueba tipo, cabecera y cierre; no es un analizador semántico ni antivirus. No hay conciliación bancaria automática.
- Siguiente puerta: operaciones y seguimiento.

## 7 — Administración y seguimiento
- Archivos: `Admin/{OperationsController,CatalogManagementController,SettingsController}`, `AdministrationService`, `OrderService`, `TrackingService`, `TrackingController`, policies, `StatusLabel`, vistas `admin/*`, `tracking/*`, JS de intercambio privado.
- Implementado: dashboard real, búsqueda/filtros/paginación/orden, expediente único, catálogo/fotos/adicionales/tarifas/configuración/usuarios, auditoría sin secretos; fabricación general, espera/reanudación, retiro/despacho/evidencia; código hash, límites de intentos, expiración y recuperación con rotación.
- Pruebas: `AdministrationTest`, `CommercialIntegrityTest`, `PaymentOrderTest`; roles, último administrador, cifrado/auditoría, token ajeno/caducado/revocado, flujo completo de retiro/transporte.
- Limitación: no se implementa una verificación automática de identidad; el personal debe comprobarla antes de renovar acceso. No se envían notificaciones externas.
- Siguiente puerta: validación técnica final y puesta en marcha.

## 8 — Validación y entrega
- Archivos: `README.md`, `docs/{pruebas,etapas,despliegue,pendientes}.md`, `scripts/browser-smoke.mjs`, suites `tests/Feature/*` y `tests/Unit/*`.
- Verificaciones: suite PostgreSQL, Pint, build, resolución/auditoría Composer, navegador Edge en 390/1440 y flujo especial→solicitud→seguimiento con datos ficticios. Resultados exactos en `pruebas.md`.
- Git: repositorio inicializado en rama `main`; secretos y artefactos excluidos, sin commits ni staging.
- **Puesta en producción no declarada completa**: faltan conexión local del usuario, datos comerciales, prueba de aceptación del taller, despliegue HTTPS y restauración/carga real. El código permite recorrer los escenarios en pruebas dedicadas.
- Siguiente etapa operativa: configurar `.env`, migrar esa base nueva, crear administrador y validar cada escenario con información confirmada del taller.
