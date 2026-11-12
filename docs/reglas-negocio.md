# Reglas de negocio

RN-01–04: carrito multi-línea; identidad normalizada por producto, medidas, adicionales, especial y observaciones de fabricación; idénticas se agrupan. Cada línea conserva su cantidad y configuración.

RN-05–09: solo tarifas confirmadas con fórmula aprobada. Ausencia de precio = pendiente; especial requiere revisión. Transporte se cotiza manualmente y nunca se representa como cero mientras está pendiente.

RN-10–14: no emitir ni pagar con pendientes; cliente acepta versión vigente; transferencia del 100%; comprobante = en verificación, no pagado; permiso explícito para verificar.

RN-15–18: no comenzar sin pago completo verificado; fabricación del pedido entero, sin estados por unidad; pedido completo terminado antes de preparar entrega.

RN-19–22: seguimiento solo con secreto; versión aceptada y precios históricos inmutables; cambios registran fecha/autor; importe pagado nunca se cambia silenciosamente.

RN-23–25: solicitud no equivale a fabricación; pago/fabricación/entrega independientes; transiciones válidas verificadas en transacción con bloqueo.

## Convenciones provisionales
- Dimensiones cm exactas hasta dos decimales; superficie = largo×ancho/10.000 con BCMath.
- Rango `[mínimo inclusivo, máximo exclusivo)`: el ancho en el límite superior pertenece al rango siguiente.
- Redondeo técnico mitad hacia arriba al CLP unitario, luego multiplicación por cantidad. No sumar impuestos supuestos. Requiere validación comercial.
- Adicional automático únicamente por cubierta, precio confirmado fijo por unidad; cualquier otra modalidad es manual hasta definirla.
- Cambios de propuesta generan nueva versión y justificación. Tras aceptación solo se reabre si no hay comprobantes y el pago sigue pendiente: se bloquea el pedido anterior conservando sus importes y se requiere aceptar una versión nueva. Con comprobantes/pagos se bloquea la modificación en el MVP. No se reutiliza aceptación para otro importe.
- Comprobante SHA-256 único para prevenir reutilización exacta; la verificación humana debe comprobar ingreso bancario real.
