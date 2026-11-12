# Análisis de todas las vistas

Fecha: 12-11-2026. Alcance: experiencia de cliente y trabajador, claridad comercial, adaptación móvil, formularios, accesibilidad básica y mantenibilidad de las plantillas.

## 1. Conclusión

La aplicación tiene una identidad visual coherente, navegación funcional y una base de formularios y estados útil. El mayor beneficio inmediato está en **evitar información desactualizada o incompleta, conservar el trabajo del usuario y acercar las acciones a la tarea actual**.

Las prioridades son: precio visible después de editar medidas, recuperación de formularios con errores, descripción completa de materiales especiales, resultado del configurador en móvil, listados administrativos móviles y coherencia del listado de pagos. Después conviene ordenar el panel, mejorar mensajes y refinar la presentación.

## 2. Método y alcance real

- Se revisaron las **25 plantillas Blade**, el CSS compartido, la navegación, el componente Livewire, el JS público y los controladores necesarios para entender qué información recibe cada pantalla.
- Se hicieron **72 comprobaciones de pantallas/estados en Edge**, con anchos de **390 y 1440 px**, incluyendo público, carrito vacío/con productos, edición, envío, confirmación, seguimiento y administración.
- Se usó exclusivamente `cubiertas_views_review_test`, una base PostgreSQL nueva y aislada. Se cargaron los escenarios demostrativos y una configuración sintética adicional para examinar precios y pantalla bancaria. **No se modificaron los datos de desarrollo ni su configuración comercial.**
- Se probó un envío inválido del formulario administrativo de catálogo utilizando un código ya existente: la validación impidió guardar, lo que permitió examinar la recuperación de la interfaz.
- Los scripts y capturas de auditoría se guardaron fuera del repositorio, en el directorio temporal `opencode/cubiertas-view-review`. No se incluyeron contraseñas ni códigos privados en el informe.
- Para completar el recorrido de muchas pantallas se usó caché en memoria por petición en ese servidor temporal, después de que el límite de intentos interrumpiera el recorrido automatizado. Este análisis **no constituye una prueba de rate limiting**.
- No se cambiaron las vistas, los estilos ni las reglas de negocio como parte del análisis. Tampoco se ejecutó nuevamente la suite de negocio: la verificación de esta tarea fue de interfaz.

### Resultados generales

- No hubo desbordamiento horizontal del documento en las 72 comprobaciones.
- No se observaron excepciones JavaScript en el recorrido completado.
- Sí existen tablas que necesitan desplazamiento horizontal interno en móvil: que la página no se desborde no significa que toda la información sea visible.
- Los controles inspeccionados tienen etiquetas asociadas mediante `<label>` envolvente; no corresponde afirmar que carecen de etiquetas por no usar `for`/`id`.
- Se revisaron visualmente capturas de catálogo, configurador, configuración y operaciones. No se hizo una auditoría WCAG completa, prueba con lector de pantalla ni un ensayo completo de teclado/zoom.

## 3. Fortalezas a conservar

1. Paleta verde, fondos neutros, tarjetas y espaciado consistentes.
2. Tipografía del sistema y frontend ligero, sin dependencia de una SPA.
3. Etiquetas visibles, enlace para saltar al contenido y estilos de foco.
4. Estados identificados con texto; no dependen únicamente del color.
5. Diferenciación explícita entre precio pendiente, cotización aceptada y comprobante en verificación.
6. Confirmación del transporte manual y distinción entre transportista y recepción del cliente.
7. Formatos monetarios CLP y medidas presentes en las líneas.
8. Archivos privados y controles de autorización para acciones administrativas.
9. Datos demostrativos identificados y ausencia de imágenes o datos comerciales presentados como reales.

## 4. Mejoras de prioridad alta

### A-01 — Invalidar el precio visible cuando cambian las medidas

**Evidencia reproducida:** con una tarifa sintética en la base de pruebas, 150 × 60 cm mostró $9.000. Al cambiar el largo a 200, la pantalla continuó mostrando $9.000 junto al botón «Agregar al carrito».

Las entradas usan `wire:model` diferido y la limpieza de `$preview` sucede en el hook del servidor. El navegador puede seguir mostrando el resultado anterior mientras mantiene cambios locales pendientes de enviar.

- Referencias: `resources/views/livewire/cover-configurator.blade.php:3-12`, `app/Livewire/CoverConfigurator.php:60-83`.
- Impacto: el usuario puede interpretar que el precio mostrado corresponde a la configuración editada.
- Propuesta: invalidar visiblemente el resultado al editar una propiedad que afecte la configuración y pedir recalcular antes de agregar. Mantener el recálculo del servidor.
- Aceptación: después de cambiar largo, ancho, cantidad, acabado o adicionales, no se presenta el precio anterior como actual; la configuración que se agrega coincide con el último resultado confirmado.
- Esfuerzo: medio.

El backend ya recalcula al agregar: el hallazgo es de coherencia de la interfaz, no evidencia de que se guarde un precio arbitrario del navegador.

### A-02 — Conservar y contextualizar formularios después de un error

**Evidencia reproducida:** en «Crear acabado» se ingresó un nombre y un código duplicado. Después de la validación, nombre y código quedaron vacíos, el acordeón se cerró y el resumen superior mostró `The code has already been taken.`

- Referencias: `resources/views/admin/partials/{product,rate,work,user}-form.blade.php:1`, `resources/views/admin/settings.blade.php:2-6`, `resources/views/admin/operations/show.blade.php:3-15`, `resources/views/components/layout.blade.php:27`.
- Impacto: pérdida del trabajo escrito y dificultad para encontrar el formulario que falló, especialmente en páginas con muchos formularios.
- Propuesta: conservar entradas no sensibles con `old()`, identificar el formulario/registro que falló, usar bolsas de errores por formulario o nombres contextualizados, reabrir su sección y enfocar el primer campo inválido. No repoblar contraseñas.
- Añadir mensajes junto al campo y `aria-invalid`/`aria-describedby`; mantener un resumen con enlaces a esos campos.
- Aceptación: el formulario fallido permanece abierto, conserva sus valores y muestra el error junto al control correcto, sin llenar los otros formularios con esos mismos valores.
- Esfuerzo: medio.

No basta con agregar `old('name')` indiscriminadamente: varias tarjetas reutilizan los mismos nombres de campo.

### A-03 — Mostrar la especificación completa de materiales especiales

**Evidencia en código:** se solicita y conserva `material_description`, pero el carrito y el componente de líneas muestran nombre, medidas, notas y adicionales, sin esa descripción específica.

- Referencias: `resources/views/livewire/cover-configurator.blade.php:3`, `resources/views/cart.blade.php:2`, `resources/views/components/quote-lines.blade.php:2`.
- Impacto: dos materiales especiales pueden parecer iguales en pantalla aunque sus descripciones sean distintas; el trabajador no dispone de toda la especificación desde el expediente.
- Propuesta: mostrar descripción del especial, observaciones diferenciadas y presencia de referencia adjunta en carrito, revisión del cliente y expediente. Las imágenes deben conservar acceso privado autorizado.
- Aceptación: una solicitud especial con descripción y referencia puede revisarse completamente antes de enviarla y desde el expediente; dos configuraciones distintas resultan distinguibles.
- Esfuerzo: pequeño/medio.

### A-04 — Acercar el resultado y la acción del configurador en móvil

**Evidencia reproducida:** en un viewport de 390 × 900, «Agregar al carrito» quedó aproximadamente a **1.993 px del inicio de la ventana**, después del formulario y de la tarjeta «Antes de medir». Al calcular no se desplaza el foco al resultado.

- Referencias: `resources/views/livewire/cover-configurator.blade.php:10-13`, `resources/css/site.css:20-23`.
- Impacto: la persona puede pulsar «Revisar cotización» y no percibir dónde apareció la respuesta.
- Propuesta: ordenar en móvil formulario → resultado → ayuda. Llevar foco/scroll al resultado cuando aparece; valorar un resumen compacto persistente sin tapar campos ni mensajes.
- Aceptación: al calcular en un teléfono se percibe inmediatamente el resultado y la acción siguiente; no hace falta atravesar la ayuda para encontrarla.
- Esfuerzo: pequeño/medio.

### A-05 — Diseñar tarjetas móviles para los listados y líneas

**Evidencia reproducida:** el listado de cotizaciones tiene un área visible de aproximadamente **356 px** y contenido de **589 px**. La columna de acción queda fuera de la vista inicial. En los detalles, algunas tablas tienen unos 305 px visibles para 387–401 px de contenido.

- Referencias: `resources/views/admin/operations/index.blade.php:17-42`, `resources/views/components/quote-lines.blade.php:2`, `resources/css/site.css:21`.
- Impacto: para abrir un expediente o relacionar un importe con su cubierta se necesita desplazamiento lateral; los nombres largos vuelven las filas muy altas.
- Propuesta: mantener tabla en escritorio y usar tarjetas etiquetadas en móvil: número, cliente, importe, estados separados y acción visible. Para líneas, nombre/especificación, medidas, cantidad, unitario y subtotal en pares etiqueta–valor.
- Aceptación: número, estado principal, importe disponible y acción se leen sin scroll lateral a 390 px; no se pierden campos al cambiar de presentación.
- Esfuerzo: medio.

### A-06 — Hacer operativo y coherente el listado de pagos

**Evidencia reproducida:** el filtro «Pendiente» devuelve «No hay registros para estos filtros», aun existiendo un pedido aceptado pendiente de transferencia en los escenarios.

La consulta de pagos exige `whereHas('payments')`, por lo que excluye los pedidos pendientes sin comprobante. Además, pagos reutiliza las columnas genéricas de cotizaciones y no prioriza importe esperado, fecha de carga o acción de verificación.

- Referencias: `resources/views/admin/operations/index.blade.php:9,19-35`, `app/Http/Controllers/Admin/OperationsController.php:35-40`.
- Propuesta: decidir una presentación consistente: incluir pedidos por pagar además de comprobantes, o definir la sección como «Comprobantes» y ofrecer únicamente sus estados. En ambos casos, mostrar los campos operativos adecuados a la tarea.
- Aceptación: cada opción del filtro corresponde al conjunto que el listado consulta; el pago pendiente del escenario DEMO-FLOW-06 puede encontrarse en la sección destinada a pendientes, y el comprobante DEMO-FLOW-07 conduce claramente a su verificación.
- Esfuerzo: medio; requiere ajustar conjuntamente consulta y vista.

## 5. Mejoras transversales de prioridad media

### M-01 — Navegación propia del panel

El panel conserva cabecera pública, barra de personal, menú administrativo y pie público. En móvil esto acumula varias filas de navegación. Separar layouts público/administrativo, marcar la sección activa con `aria-current="page"`, y disponer navegación compacta en teléfono y lateral en escritorio. Se mantiene una sola aplicación Laravel.

Referencias: `components/layout.blade.php:13-30`, `components/admin-nav.blade.php:1`.

### M-02 — Un idioma coherente y mensajes orientados a la tarea

Se verificaron mensajes de validación en inglés y paginación «Previous / Next». El expediente presenta claves como `carrier`, `destination`, `dispatched_at` y `notes`; la auditoría muestra nombres técnicos de entidades/campos.

Traducir validaciones y atributos, paginación y etiquetas de despacho/auditoría. Sustituir textos internos como «El precio se calcula en el servidor» por información útil: qué incluye, qué está pendiente y qué sigue.

Referencias: `components/layout.blade.php:27`, `admin/operations/show.blade.php:14`, `admin/settings.blade.php:6`, `livewire/cover-configurator.blade.php:12`, llamadas a `links()`.

### M-03 — Estado actual y siguiente acción al principio

El expediente inicia con el estado de la cotización; pago, fabricación y entrega aparecen bastante después. En la captura móvil de pago, la página completa tenía unos **4.840 px** de alto. Llevar arriba número, total de versión vigente, tres estados independientes, aviso de espera y acción principal. Organizar detalle e historial mediante secciones con anclas; no ocultar la información necesaria para revisar el pedido completo.

El seguimiento del cliente también debería responder primero «qué está pasando» y «qué debo hacer». No inventar plazos de fabricación o fechas estimadas.

Referencias: `admin/operations/show.blade.php:1-16`, `tracking/show.blade.php:1-7`.

### M-04 — Formularios progresivos y mejor resumen de solicitud

En solicitud se muestran todos los campos de transporte incluso al seleccionar retiro. Ocultarlos/deshabilitarlos visualmente cuando no correspondan, explicar la obligatoriedad de comuna al elegir transporte y conservar la información si la persona cambia de modalidad durante la edición.

Mostrar un resumen plegable de las cubiertas y cantidades, no solo sus contadores. Diferenciar «sin importes confirmados» de un importe conocido de cero y mantener la advertencia de pendientes.

Referencia: `quotes/create.blade.php:3-4`.

### M-05 — Dashboard con accesos a tareas reales

Las tarjetas muestran contadores, pero no son enlaces. Hacerlas navegables con filtros adecuados. Distinguir fabricación pendiente de fabricación **habilitada por pago verificado**, sin cambiar silenciosamente la regla comercial ni presentar ambos conjuntos como equivalentes.

Referencias: `admin/dashboard.blade.php:1`, `app/Http/Controllers/Admin/OperationsController.php:20-26`.

### M-06 — Catálogo con estados vacíos correctos

Una búsqueda sin coincidencias muestra «El taller aún no ha publicado acabados», aunque existen productos. Separar catálogo vacío de búsqueda sin resultados; incluir «Limpiar búsqueda». Añadir filtros simples por acabado/color y disponibilidad, conteo y acción explícita en tarjeta cuando corresponda.

Referencia: `catalog/index.blade.php:2-4`.

### M-07 — Ayuda para guardar y utilizar el acceso privado

La confirmación presenta un código de 64 caracteres sin copiar código/enlace. Agregar botones accesibles con feedback, mantener visible el número de solicitud y explicar cómo volver al seguimiento. En consulta, permitir mostrar/ocultar el código pegado y ayudar con formato sin rebajar la autenticación. La recuperación sigue requiriendo verificación de identidad.

Referencias: `quotes/received.blade.php:1`, `tracking/lookup.blade.php:1`.

### M-08 — Gestión administrativa menos extensa

Configuración reúne datos públicos, fórmula, límites, banco, usuarios y auditoría. La captura móvil medía unos **4.370 px** con los usuarios cerrados. Organizar por pestañas o secciones independientes con guardado propio y errores localizados. Un guardado parcial debe preservar expresamente las otras secciones.

Catálogo y tarifas deberían facilitar búsqueda/filtros y mostrar estado de confirmación separado de actividad. No elegir un acabado implícitamente al crear una tarifa; presentar «Selecciona un acabado». Usar un área de texto para «Información del taller», y tipos `email`/`tel` donde correspondan.

Referencias: `admin/settings.blade.php:1-6`, `admin/catalog.blade.php:1-3`, `admin/rates.blade.php:1`, `admin/partials/rate-form.blade.php:1`.

### M-09 — Archivos y acciones contextuales

Mostrar archivo seleccionado, tamaño, progreso y estado de carga; permitir identificar la referencia ya guardada al editar. Para comprobantes, una visualización autenticada opcional puede reducir descargas sin volver públicos los archivos. Las verificaciones deben seguir siendo humanas.

Usar textos diferentes para «Agregar al carrito» y «Guardar cambios». Explicar por qué una acción no está disponible. Las acciones terminales como rechazo o retirada de una foto necesitan un contexto claro de lo que se afectará.

Referencias: `livewire/cover-configurator.blade.php:8-12`, `auth/login.blade.php:4-6`, `tracking/show.blade.php:5-6`, `admin/operations/show.blade.php:3-15`, `admin/catalog.blade.php:2`.

## 6. Inventario completo: mejora principal por plantilla

Las rutas de esta tabla son relativas a `resources/views/`.

| Plantilla | Qué mejorar primero |
|---|---|
| `components/layout.blade.php` | Separar contexto público/panel, sección activa, errores localizados y navegación móvil compacta. |
| `components/admin-nav.blade.php` | Indicar sección actual, mejorar jerarquía y adaptación móvil. |
| `components/quote-lines.blade.php` | Descripción completa de especiales y tarjetas móviles; totales claramente asociados. |
| `home.blade.php` | Conservar identidad; reducir longitud móvil, mejorar acceso al catálogo completo y usar enlaces de contacto si están configurados. |
| `catalog/index.blade.php` | Estado de búsqueda vacío correcto, filtros y CTA de tarjeta. |
| `catalog/show.blade.php` | Galería con imagen principal/miniaturas, características legibles y alternativa si no está disponible. |
| `configurator.blade.php` | Indicar paso del flujo y diferenciar alta de edición. |
| `livewire/cover-configurator.blade.php` | Invalidación de precio, resultado visible en móvil, errores por campo y feedback de adjunto/carga. |
| `guide.blade.php` | Diagrama más claro con SVG, numeración visible y ejemplo de medidas sin inventar tolerancias. |
| `cart.blade.php` | Mostrar especial completo/referencia, edición y separación más claras, resumen accesible sin recorrer todas las líneas. |
| `quotes/create.blade.php` | Transporte condicional y resumen real de cubiertas antes de enviar. |
| `quotes/received.blade.php` | Copiar enlace/código y explicación de siguiente paso; código como dato, no etiqueta sin control. |
| `tracking/lookup.blade.php` | Ayuda al pegar código, mostrar/ocultar y contacto configurado para recuperación. |
| `tracking/show.blade.php` | Estado/siguiente acción arriba; desglose de versiones históricas y ficha bancaria fácil de copiar. |
| `auth/login.blade.php` | Mostrar/ocultar contraseña, envío en curso y mensajes junto a los controles; no añadir registro público. |
| `admin/dashboard.blade.php` | Indicadores accionables, colas filtradas y distinción de fabricación habilitada. |
| `admin/operations/index.blade.php` | Columnas propias por módulo, tarjetas móviles, filtros coherentes y botón de limpiar. |
| `admin/operations/show.blade.php` | Cabecera operativa, especificación completa, acción principal visible, despacho traducido y formularios con errores contextualizados. |
| `admin/catalog.blade.php` | Conservar formulario abierto/valores, filtros, estados vacíos y gestión visual de fotografías. |
| `admin/rates.blade.php` | Buscar por acabado, mostrar confirmación/actividad/fórmula y representar rangos con una ayuda visual sencilla. |
| `admin/settings.blade.php` | Separar secciones/tareas, tipos de campo correctos, guardado independiente y auditoría comprensible. |
| `admin/partials/product-form.blade.php` | `old()` contextual, errores inline, IDs únicos y separación clara entre demostrativo/disponible. |
| `admin/partials/rate-form.blade.php` | Acabado sin preselección implícita, formato decimal coherente con español y límites claramente explicados. |
| `admin/partials/work-form.blade.php` | Mostrar precio solo cuando aplica, distinguir habilitación de precio confirmado y conservar errores. |
| `admin/partials/user-form.blade.php` | Ayuda de contraseña conforme a validación, mostrar/ocultar, permisos destacados y formulario localizado tras fallo. |

## 7. Refinamiento visual y mantenibilidad

Prioridad posterior a los problemas funcionales:

- Definir tokens para colores semánticos de estado, tipografía, radios y espaciados. Mantener los textos de estado.
- Ajustar títulos dentro de tarjetas: un `h2` global puede resultar demasiado grande en un listado; separar estilos de título de página, sección y tarjeta.
- Introducir una distribución de dos columnas en tablet cuando sea útil; actualmente el breakpoint convierte tanto `.two` como `.three` en una sola columna.
- Dar jerarquía clara a acción primaria, secundaria y enlaces; evitar que todas las acciones del expediente compitan visualmente.
- Usar datos de contacto con enlaces `tel:`/`mailto:` únicamente cuando estén configurados.
- Presentar características y observaciones con saltos de línea legibles; no renderizar HTML arbitrario del usuario.
- Para fotografías públicas: carga diferida fuera de la primera pantalla, dimensiones y tamaños adecuados. No se midió rendimiento con un catálogo de fotografías reales.
- Añadir estados de error propios y en español para 403, 404, 419 y 429, con salida/reintento apropiados. La limitación de intentos funcionó durante el recorrido inicial, pero su página genérica es poco orientativa.
- Formatear Blade y CSS: muchas plantillas están comprimidas en una o pocas líneas, lo que dificulta revisar condiciones y formularios.
- Extraer componentes pequeños de campo/error, estado, importe, ficha de cubierta y cabecera de expediente. Preparar en controlador/presentador las acciones posibles; las reglas definitivas permanecen en servicios/policies.
- Reemplazar rutas literales por rutas nombradas para mantener navegación consistente.

No se necesita incorporar otra biblioteca visual ni cambiar el framework para realizar estas mejoras.

## 8. Orden propuesto de implementación

### Bloque 1 — Coherencia y recuperación
1. A-01: invalidación del precio visible.
2. A-02: recuperación de formulario y errores en español.
3. A-03: información completa de especiales.
4. Corregir el filtro de pagos y el estado de búsqueda sin resultados.

**Verificación:** cambiar medidas después de calcular; enviar un código duplicado; revisar dos especiales con diferente descripción; localizar un pedido sin comprobante.

### Bloque 2 — Experiencia móvil
1. Resultado/CTA del configurador cerca de la acción.
2. Tarjetas móviles para operaciones y cubiertas.
3. Transporte condicional y resumen de solicitud.
4. Copiar enlace/código y feedback de carga.

**Verificación:** flujo catálogo→configuración→carrito→solicitud→seguimiento en teléfono, sin datos perdidos ni acciones fuera de la vista útil.

### Bloque 3 — Productividad del equipo
1. Layout administrativo y navegación activa.
2. Dashboard enlazado a colas.
3. Cabecera de expediente con estado/acción actual.
4. Configuración organizada por tareas y formularios contextualizados.

**Verificación:** localizar y gestionar un comprobante, un pedido listo para fabricar y un retiro con pocos pasos, conservando historial y permisos.

### Bloque 4 — Pulido y accesibilidad
1. Galería y guía SVG.
2. Ajustes de tipografía, espaciado, estados y tablet.
3. Teclado, foco después de errores, lector de pantalla, zoom y contraste medido.
4. Nuevas capturas y pruebas de regresión del frontend.

La comprobación visual debe acompañarse de pruebas funcionales cuando se cambien consultas, interacción Livewire o recuperación de formularios. Este documento propone el trabajo; no declara implementadas las mejoras.
