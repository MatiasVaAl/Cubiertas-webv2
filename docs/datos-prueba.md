# Datos ficticios para pruebas manuales

## Carga

Con PostgreSQL configurado en `.env` y migraciones aplicadas:

```powershell
php artisan migrate
php artisan db:seed --class=FunctionalDemoSeeder
```

Solo funciona por consola con `APP_ENV=local` o `testing`. Crea **10 acabados, 14 clientes/solicitudes, 9 pedidos, 8 comprobantes PDF y 3 usuarios demostrativos**, más líneas, versiones e historial. No borra datos existentes ni cambia la configuración del taller, los datos bancarios o las tarifas. `DatabaseSeeder` sigue vacío por defecto.

Puedes repetir el comando: reconoce sus escenarios por `[DEMO-FLOW-XX]` en las observaciones, no los duplica ni reinicia el avance manual. Tampoco restablece contraseñas ni renueva códigos existentes. Los números `CT-…` se asignan por la base y pueden variar; no asumir que comienzan en CT-000001.

## Accesos

El comando indica la ubicación de un informe privado:

```text
storage/app/private/demo/{nombre-de-la-base}/accesos.json
```

Abrir ese archivo localmente para obtener las contraseñas aleatorias, números `CT-…`, códigos y enlaces privados de los 14 escenarios. No se muestran secretos en consola ni se incluyen en documentación/Git. No existe URL pública para el archivo. Si se pierde el informe, crear un administrador mediante `cubiertas:create-admin` o restablecer contraseñas desde otra cuenta administrativa; para seguimiento se puede renovar el acceso desde el expediente tras la comprobación de identidad.

| Usuario | Uso |
|---|---|
| `admin.demo@example.invalid` | Administrador; catálogo, tarifas, configuración, usuarios y operaciones |
| `operations.demo@example.invalid` | Trabajador operativo sin permiso para aprobar pagos |
| `payments.demo@example.invalid` | Trabajador con permiso explícito para verificar pagos |

Son direcciones reservadas de ejemplo, no correos reales. Las contraseñas son diferentes y se generan en cada instalación. Si ya existen esas cuentas, se conservan tal cual; si sus permisos cambiaron, completar escenarios nuevos puede requerir revisar esos permisos desde el panel.

## Catálogo (10 registros)

| Código | Acabado ficticio | Estado inicial de demostración |
|---|---|---|
| DEMO-ROBLE | Roble | Habilitado para solicitudes |
| DEMO-PIEDRA | Piedra clara | Habilitado para solicitudes |
| DEMO-GRIS | Gris suave | Habilitado para solicitudes |
| DEMO-BLANCO | Blanco mate | Habilitado para solicitudes |
| DEMO-NOGAL | Nogal | Habilitado para solicitudes |
| DEMO-NEGRO | Negro mate | Habilitado para solicitudes |
| DEMO-ARENA | Arena | Habilitado para solicitudes |
| DEMO-CEMENTO | Cemento | Habilitado para solicitudes |
| DEMO-VERDE | Verde oliva | No disponible |
| DEMO-TERRACOTA | Terracota | No disponible |

Los acabados están marcados como demostrativos: nunca generan cotizaciones automáticas y están ocultos en producción. La disponibilidad anterior solo sirve para probar el software, no representa stock del taller. No se agregan fotografías que pretendan ser reales. El seeder simple `DemonstrationSeeder` también crea únicamente estos 10 acabados.

## Escenarios (14 solicitudes)

Buscar `Cliente DEMO` en el listado administrativo; las observaciones contienen la referencia. Los importes siguientes son **valores sintéticos elegidos para pruebas, no tarifas de venta**.

| Referencia | Estado inicial | Modalidad | Total de prueba CLP | Qué comprobar |
|---|---|---|---:|---|
| DEMO-FLOW-01 | Pendiente de revisión | Retiro | Pendiente | Resolver acabado y precio manualmente |
| DEMO-FLOW-02 | Material especial pendiente | Retiro | Pendiente | Revisar disponibilidad y factibilidad |
| DEMO-FLOW-03 | En revisión, transporte pendiente | Transporte | Pendiente | El costo pendiente no aparece como cero; cubierta conocida $36.000 |
| DEMO-FLOW-04 | Esperando información | Retiro | Pendiente | Enviar información desde seguimiento |
| DEMO-FLOW-05 | Esperando aceptación | Retiro | $70.000 | Dos configuraciones: una con 4 unidades agrupadas y una especial con 1 |
| DEMO-FLOW-06 | Aceptada; pago pendiente | Retiro | $45.000 | Ver total y solicitar cambio antes de comprobantes |
| DEMO-FLOW-07 | Comprobante en verificación | Retiro | $16.000 | Descargar PDF privado y probar permisos de verificación |
| DEMO-FLOW-08 | Pago observado | Retiro | $34.000 | Consultar observación; nuevo comprobante requiere banco configurado |
| DEMO-FLOW-09 | Pagado; fabricación pendiente | Retiro | $54.000 | Iniciar fabricación completa |
| DEMO-FLOW-10 | En fabricación y en espera | Retiro | $19.000 | Reanudar antes de terminar fabricación |
| DEMO-FLOW-11 | Fabricación terminada, listo para retiro | Retiro | $40.000 | Confirmar retiro del pedido completo |
| DEMO-FLOW-12 | Entregado al transportista | Transporte | $68.000 | Distinguir despacho de recepción del cliente |
| DEMO-FLOW-13 | Entregado al cliente | Retiro | $22.000 | Revisar historial completo sin alterar importes |
| DEMO-FLOW-14 | Entrega confirmada con evidencia simulada | Transporte | $51.000 | Revisar condiciones, despacho y recepción |

Los precios unitarios se resuelven manualmente en los escenarios de muestra; el cálculo automático permanece con su configuración anterior y no se crean tarifas ficticias. Las medidas varían entre los registros y se ajustan a límites ya configurados, si los hay. Los contactos llevan `DEMO-NO-LLAMAR` y las direcciones se identifican como ficticias.

## Pagos y comprobantes

Los 8 PDF se generan en el disco privado con el texto **DEMOSTRACION - SIN VALIDEZ DE PAGO**. No son comprobantes bancarios reales. La preparación de fixtures crea un registro sintético en verificación; las aprobaciones/observaciones y la fabricación posterior recorren los servicios del sistema. El historial identifica expresamente la simulación. No se instala ninguna cuenta bancaria ni se simula una conciliación real.

Para probar cargas nuevas desde la interfaz se necesitan datos bancarios completos configurados en esa base local; el seeder no los inventa ni los modifica.

## Criterios de aceptación

- Hay 10 acabados demostrativos y 14 solicitudes identificadas, con 9 pedidos y 8 PDF privados.
- Los estados respetan pago total verificado antes de fabricar y fabricación terminada antes de entregar.
- Cada pedido referencia una versión con el mismo importe; el transporte pendiente no es gratuito.
- El usuario operativo no puede aprobar pagos y el verificador sí tiene permiso explícito.
- Los códigos del informe abren exclusivamente su solicitud y solo hay hashes en la base.
- Repetir la carga no duplica registros, secretos ni archivos, y conserva cambios manuales.
- El seeder rechaza producción; la configuración comercial y los registros ajenos se conservan.

## Verificación automatizada

`tests/Feature/FunctionalDemoSeederTest.php` comprueba el conjunto, estados, accesos privados, agrupación, configuración/sesión intactas, repetición, bloqueo en producción y rollback/limpieza de archivos ante fallo. Ejecutar exclusivamente con una base PostgreSQL dedicada terminada en `_test`:

```powershell
php artisan migrate --env=testing
php artisan test tests/Feature/FunctionalDemoSeederTest.php
```

## Carga realizada en desarrollo

Se ejecutó `php artisan db:seed --class=FunctionalDemoSeeder --no-interaction` sobre la conexión local ya configurada. Se generaron las solicitudes **CT-000001 a CT-000014**, correspondientes en orden a los 14 escenarios de la tabla. El informe quedó en `storage/app/private/demo/cubiertas_temuco/accesos.json`. Los accesos secretos no se imprimieron en la terminal.

La verificación automatizada se realizó en otra base PostgreSQL dedicada; no se ejecutaron pruebas de integración sobre desarrollo. Para ejecutar estas pruebas usar una base `_test` reservada para la suite, sin escenarios persistidos previamente por el seeder manual.
