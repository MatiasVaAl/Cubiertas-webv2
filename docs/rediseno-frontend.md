# Rediseño frontend — implementación y verificación

## Referencia recibida

Después del avance inicial de traducciones, el usuario adjuntó una lámina con ejemplos de inicio, catálogo, cotizador de tres columnas, carrito, solicitud y móvil. Se tomaron su verde bosque, crema y salvia, tarjetas de acabado, numeración de secciones, controles compactos y resumen lateral.

Se conservaron las reglas propias del sistema aunque la imagen muestre otra cosa: teléfono obligatorio, correo opcional, precio sujeto a tarifas/fórmula confirmadas y transporte manual. No se copiaron datos de contacto, nombres de productos, horarios, precios ni fotografías de la referencia como información real del taller.

## Inspección y límites

Antes de modificar se revisaron `CoverConfigurator`, `CartService`, `PricingService`, rutas, controladores, modelos, estilos, plantillas y pruebas existentes. Se mantienen Laravel/Livewire/Blade/Tailwind, sin nuevos paquetes.

**Sin cambios en servicios comerciales, modelos, migraciones o esquema PostgreSQL.** No se ejecutaron migraciones ni seeders contra desarrollo. Las pruebas y fixtures se ejecutaron únicamente en bases dedicadas terminadas en `_test`.

## Funcionalidades implementadas

### Cotizador `/cotizar`

- Tres columnas en escritorio: contacto/guía, configurador principal y cotización. En tablet la ayuda pasa debajo; en teléfono el orden es configuración → resumen/acción → ayuda.
- Selección mediante tarjetas reales del catálogo, con nombre, descripción, disponibilidad, imagen o placeholder identificado. Selección con borde/indicador y radio nativo accesible; acabados no disponibles deshabilitados.
- Seis tarjetas inicialmente y acceso «Ver todos los acabados» para no alargar innecesariamente el teléfono. La selección actual permanece visible aunque inicialmente no esté entre los primeros seis.
- Tres secciones: acabado, medidas/cantidad y detalles adicionales. Materiales especiales conservan descripción, referencia privada y revisión manual.
- Largo en metros por defecto, con opción centímetros; ancho en centímetros. Los botones cambian el largo 10 cm y el ancho 1 cm, sin establecer límites de fabricación.
- Cantidad con +/− y entrada manual; conserva mínimo 1 y máximo técnico existente.
- Resumen reactivo con acabado, dimensiones canónicas, superficie geométrica exacta, cantidad, adicionales y precio confirmado por el servicio existente.
- Estado pendiente «Precio por confirmar», sin mostrar $0 como venta ni hacer afirmaciones tributarias.
- El precio anterior se oculta mientras hay cambios locales o una petición en curso. No se presenta como precio actual durante la edición.
- Única acción principal: «Agregar al carrito». Después de agregar, confirmación y contador actualizado sin salir del cotizador. En edición, «Guardar cambios» vuelve al carrito.

### Conversión exacta de medidas

La conversión pertenece a la capa interactiva, no cambia la estrategia de precios:

- `2,40 m` → `240.00 cm`.
- Hasta cuatro decimales en metros representan los dos decimales canónicos de centímetros: `2,4001 m` → `240.01 cm`.
- Se utiliza BCMath, sin aritmética flotante para convertir medidas.
- Cambiar de unidad conserva la medida, no reinterpreta el valor anterior. La acción de cambio procesa primero los cambios pendientes de la unidad anterior, verificada en navegador con escritura seguida inmediatamente del cambio de unidad.
- La superficie es geometría de presentación; los importes siguen calculándose en `PricingService`.

### Carrito `/carrito`

- Tarjetas comerciales con imagen, medidas, superficie por unidad, cantidad, trabajos adicionales, notas y descripción del especial.
- Resumen lateral en escritorio, apilado en móvil, con configuraciones/unidades, importes conocidos y transporte pendiente.
- Controles +/− y cantidad manual conservan la ruta PATCH y `CartService`; indican envío y bloquean nuevas pulsaciones mientras se guarda.
- Agrupación idéntica, edición, eliminación y separación individual conservadas.
- Valores completamente pendientes se presentan como «Total definitivo pendiente de confirmación». Un importe parcial conocido sigue visible y no se etiqueta como total final.
- Referencias privadas permanecen privadas; repetir agregado del mismo especial con la misma referencia no crea rutas nuevas que impidan agruparlo.

### Solicitud, catálogo e inicio

- Solicitud con ficha de contacto, entrega condicional y resumen de cubiertas. Retiro oculta campos de transporte; transporte requiere ciudad/comuna. El servidor mantiene sus validaciones.
- Encabezado compacto, navegación activa y menú móvil nativo. Contador de carrito Livewire compartido.
- Catálogo con tarjetas reutilizables, CTA y búsqueda vacía distinguida de catálogo sin publicar. Ficha de acabado con imágenes existentes y alternativa si no está disponible.
- Inicio conserva su contenido y añade tarjetas/beneficios coherentes. Sin fotografía real proporcionada se conserva la ilustración explícitamente demostrativa, sin presentar una cocina ficticia como trabajo del taller.

### Contacto configurable

Se agregaron campos opcionales en la configuración existente para `contact.whatsapp`, `contact.instagram`, `contact.facebook` y `contact.map_url`. Se almacenan en el JSON de contacto ya disponible; no hay migración.

El único ajuste de validación administrativa fue ampliar esa lista de campos y aceptar enlaces HTTPS válidos. Se conserva autorización administrativa y servicio de guardado existente. El cotizador no activa enlaces faltantes o inseguros ni deriva una cuenta de WhatsApp desde un teléfono no confirmado. El acceso a WhatsApp es un enlace externo, no una integración de mensajería.

## Componentes y archivos

| Grupo | Archivos principales |
|---|---|
| Estado interactivo | `app/Livewire/CoverConfigurator.php`, `app/Livewire/CartCounter.php` |
| Geometría de presentación | `app/Support/CoverMeasurements.php` |
| Componentes Blade | `icon`, `product-image`, `finish-card`, `measure-guide`, `field-error`, `quantity-stepper`, `contact-help`, `public-links`, `quote-amount` |
| Vistas principales | `resources/views/{configurator,cart,quotes/create,catalog/index,catalog/show,home}.blade.php`, `livewire/{cover-configurator,cart-counter}.blade.php` |
| Layout/estilos | `resources/views/components/layout.blade.php`, `resources/css/{app,shop}.css` |
| Datos de presentación | `CartController` y `QuoteController`: carga anticipada de fotografías para sus resúmenes; sin cambiar operaciones de negocio |
| Contacto | `Admin/SettingsController.php`, `resources/views/admin/settings.blade.php`, nombres de campo en `lang/es/validation.php` |
| Pruebas | `VisualConfiguratorTest`, `CoverMeasurementsTest`, ajustes de expectativas de interacción en `CommercialIntegrityTest` y `PricingTest` |
| Navegador | `scripts/browser-smoke.mjs`, `scripts/browser-shop.mjs` |

Los cambios de expectativas existentes se limitan al comportamiento pedido: agregar ya no redirige; editar sí. Las comprobaciones de persistencia, importes, agrupación, archivos privados y autorización se mantienen.

## Resultados ejecutados

| Verificación | Resultado final |
|---|---|
| `php artisan test` | **51 pruebas, 413 aserciones correctas**, última ejecución 7,14 s |
| `php vendor/laravel/pint/builds/pint --test` | Correcto |
| `npm run build` | Vite correcto; CSS/JS y manifest generados |
| `node --check scripts/browser-shop.mjs` | Sintaxis correcta |
| `scripts/browser-smoke.mjs` | **18 comprobaciones**, seis páginas × 390/768/1440; sin desborde de documento ni excepciones reportadas; especial→solicitud→seguimiento correcto |
| `scripts/browser-shop.mjs` con fixtures dedicados | **44 comprobaciones correctas** en 390/768/1440; sin excepciones JavaScript reportadas |

### Cobertura del recorrido visual

- Tarifas sintéticas exclusivamente en `_test`: precio de 2,40 metros y superficie 1,44 m².
- Cambio de medida, ocultación del precio anterior, cambio inmediato de unidad y recálculo.
- Configuración inválida rechazada y corregida.
- Selección/expansión de acabados, orden de columnas y menú móvil.
- Agregar sin abandonar cotizador, confirmación y contador actualizado.
- Dos cubiertas idénticas agrupadas, +/− en carrito, separación, edición y eliminación.
- Carrito mixto con subtotal conocido; carrito solo pendiente sin $0 comercial.
- Entrega condicional, solicitud con transporte y seguimiento privado.
- Login administrativo y acceso a dashboard, cotizaciones y configuración.
- Capturas de catálogo, cotizador, carrito y solicitud en los tres anchos. Se revisaron visualmente las capturas resultantes.

Se corrigió el primer intento de automatización del control +/− para esperar el documento que devuelve el servidor, no solo el cambio local del campo. También se agregó feedback/bloqueo durante ese envío.

Los fixtures de navegador usaron otra base dedicada, `cubiertas_frontend_test`, sin alterar el conjunto base de la suite ni la configuración comercial de desarrollo. Sus importes y contactos son sintéticos. Servidores y PostgreSQL temporal se detuvieron al terminar.

## Repetir el recorrido de navegador

`browser-smoke.mjs` conserva las instrucciones del README. El recorrido profundo requiere Node, Edge/Chromium y una base PostgreSQL dedicada con migraciones y un acabado de prueba habilitado con fórmula/tarifa confirmadas exclusivamente en esa base.

Variables:
- `DB_DATABASE`: nombre dedicado terminado en `_test`, coherente con `.env.testing` y la conexión efectiva.
- `BROWSER_PRODUCT_ID`: ID del acabado de prueba tarifado.
- `BROWSER_BIN`, `PHP_BIN`: ejecutables reales.
- `BROWSER_ARTIFACTS`: carpeta temporal de capturas/resultados.
- `BROWSER_ACCESS_FILE` opcional: informe privado del seeder en esa misma base para probar el acceso administrativo. Sus secretos no se imprimen.

Ejecutar `node scripts/browser-shop.mjs`. Usa puertos 8095 y 9241; deben estar libres. No ejecutar contra desarrollo ni producción. Para esta entrega los artefactos quedaron fuera del repositorio en `opencode/cubiertas-shop-review` y `opencode/cubiertas-feedback-browser`.

## Pendientes reales

- Incorporar fotografías, contacto, redes y horarios reales cuando el taller los proporcione. Los placeholders y canales pendientes son intencionales.
- Validación comercial de fórmula/tarifas/impuestos sigue pendiente según `pendientes.md`; el rediseño no los confirma ni cambia su activación en desarrollo.
- La referencia es inspiración de distribución y estilo, no una reproducción píxel a píxel ni una fuente de información del negocio.
- No se realizó certificación WCAG, ensayo con lector de pantalla ni pruebas en teléfonos físicos. Se implementaron controles semánticos, foco, radios nativos, errores accesibles y se verificó responsive en navegador.
