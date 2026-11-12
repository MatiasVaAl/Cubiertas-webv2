# Decisiones comerciales pendientes

No se han proporcionado tarifas reales, inventario real, fotografías, contacto ni datos bancarios. Ningún valor de prueba representa una oferta comercial.

| Pendiente | Comportamiento hasta confirmar |
|---|---|
| Fórmula definitiva | m² aislada tras interfaz; desactivada por defecto, requiere confirmación administrativa |
| Tarifas y rangos exactos | Sin tarifas precargadas; rangos técnicos `[inferior, superior)` explícitos y sin solapamiento |
| Referencias $57.000/$38.000 | No usadas en el tarifario |
| Límites de fabricación | Nulos/configurables; no inventar máximos comerciales |
| Adicionales | Revisión manual salvo precio/regla confirmados por el taller |
| Especiales/disponibilidad | Revisión humana; no aceptar con líneas pendientes |
| Vigencia de cotización | Sin vencimiento comercial automático; registrar nueva versión al cambiar propuesta |
| Impuestos/documentos/redondeo | No inferir IVA ni emitir documentos tributarios; CLP entero, redondeo provisional mitad hacia arriba del unitario, luego cantidad |
| Transporte | Siempre manual, pendiente distinto de cero; condiciones en versión aceptada |
| Plazos/cancelaciones/devoluciones | No publicar promesas; modificaciones posteriores al pago requieren resolución fuera del flujo MVP |
| Dirección/horarios/contacto | Campos vacíos y mensaje de información pendiente |
| Datos bancarios | Cifrados/configurables; no mostrar instrucciones ni admitir comprobante sin configuración completa |
| Fotografías y catálogo real | Solo seed demostrativo explícito en local/testing, sin tarifas de venta |
| Recuperación de seguimiento | Preparar rotación por trabajador tras verificación de identidad fuera de la web; no recuperar solo por número o teléfono |
| Caducidad de acceso privado | Provisional técnica: credencial 90 días (`TRACKING_DAYS`), sesión autorizada dos horas. Separada de vigencia comercial; validar plazo real antes de producción |

Los límites de precisión, tamaño de archivo y cantidades aceptadas por el software son controles técnicos, no políticas del taller: dimensiones hasta dos decimales y diez enteros, hasta 10.000 unidades por configuración, 100 configuraciones por solicitud, archivos de hasta 5 MB e importes acumulados de hasta 10^15 CLP. No representan fabricación, tarifas ni capacidad real. Registrar explícitamente cualquier nueva confirmación comercial con responsable antes de habilitar producción.

## Puesta en marcha pendiente
- El usuario configuró la conexión de desarrollo en `.env`; posteriormente se comprobó su conexión y las migraciones ya aplicadas. A petición del usuario se cargaron 10 acabados y 14 escenarios ficticios con `FunctionalDemoSeeder`, sin alterar la configuración comercial. Las pruebas automatizadas siguen aisladas en una base `_test`.
- Crear el primer administrador interactivo y registrar información comercial real.
- Verificación con el taller de los doce escenarios con sus datos; lo automatizado usa importes ficticios confinados a pruebas.
- Pruebas de carga/concurrencia multiproceso, restauración y despliegue HTTPS real no ejecutadas en esta sesión.
