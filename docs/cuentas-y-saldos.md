# Guía de cuentas, adelantos y saldos

Para el administrador o responsable hotelero con permiso financiero. Necesitas una solicitud existente y datos ficticios. **Esta fase es un libro de demostración: no cobra dinero, no verifica transferencias bancarias y no emite comprobantes SUNAT.** Los pagos de prueba de Commerce se conservan aparte y no se suman como ingresos de esta cuenta.

## Abrir la cuenta de una estadía

1. Abre **Cuentas y saldos** en el panel hotelero.
2. Localiza la solicitud.
3. Pulsa **Ver cuenta**.
4. Revisa el estimado original.
5. Marca **Entiendo que esta operación es una simulación y que no se moverá dinero**.
6. Pulsa **Abrir cuenta de demostración**.

**Resultado:** se registra el cargo inicial a partir del estimado de la solicitud. Abrirla nuevamente no duplica el cargo. Consultar la pantalla antes de pulsar el botón no crea movimientos. También puedes acceder mediante **Ver cuenta** en Reservas o **Ver cuenta y saldo** en recepción.

## Registrar un adelanto o completar el saldo

La reserva debe estar confirmada. El importe no puede superar el saldo pendiente. Los medios disponibles son simulados y no solicitan información de tarjetas.

1. Abre la cuenta.
2. Selecciona **Adelanto / pago simulado** en **Movimiento**.
3. Completa **Importe en PEN**, por ejemplo `25.00` para un ejercicio.
4. Selecciona el **Medio simulado**.
5. Escribe el **Motivo del movimiento**.
6. Marca el consentimiento de simulación.
7. Pulsa **Registrar movimiento simulado**.

**Resultado:** aumenta **Pagado neto** y disminuye **Saldo pendiente**. Un reenvío del mismo movimiento no lo duplica. Estos valores representan el ejercicio, no dinero recibido por el hotel.

## Registrar cargos y descuentos

1. Selecciona **Cargo adicional** o **Descuento**.
2. Completa un importe positivo con hasta dos decimales.
3. Escribe el motivo de al menos cinco caracteres.
4. Marca el consentimiento.
5. Pulsa **Registrar movimiento simulado**.

**Resultado:** cambia **Cargos netos** y se recalcula el saldo. Un descuento no puede hacer negativos los cargos netos. Si se descuenta después de haber pagado, puede aparecer **Saldo a favor del huésped**; no se devuelve dinero automáticamente.

## Registrar una devolución simulada

Una devolución no puede superar el pagado neto disponible. Cancelar la reserva no genera una devolución automática ni borra la cuenta.

1. Selecciona **Devolución simulada**.
2. Completa el importe a devolver.
3. Selecciona el medio simulado.
4. Escribe el motivo.
5. Marca el consentimiento.
6. Pulsa **Registrar movimiento simulado**.

**Resultado:** disminuye **Pagado neto**. El saldo puede volver a quedar pendiente si no se corrigen también los cargos correspondientes. Es una simulación contable y no una orden de devolución bancaria.

## Corregir un movimiento

La anulación conserva el original y añade su efecto inverso. No es una nota de crédito SUNAT ni una devolución bancaria. No se anula una contrapartida; registra una nueva corrección según corresponda.

1. Localiza el movimiento en el historial.
2. Pulsa **Anular con motivo**.
3. Revisa el identificador del original.
4. Explica el **Motivo de la anulación**.
5. Pulsa **Registrar contrapartida de demostración**.

**Resultado:** aparece una nueva línea que referencia el original y se recalculan los saldos. Si la corrección dejaría pagos o cargos netos negativos, se rechaza: debes resolver primero los movimientos relacionados. No se puede anular el mismo original dos veces.

## Imprimir una constancia interna

1. Localiza el movimiento.
2. Pulsa **Constancia interna**.
3. Revisa su estado, importe y motivo.
4. Pulsa **Imprimir constancia interna**.
5. Selecciona la impresora o **Guardar como PDF** en el navegador.

**Resultado:** obtendrás una representación interna marcada **SIN VALIDEZ TRIBUTARIA**. Un movimiento anulado se identifica como tal. Este documento no acredita un pago real.

## Consultar movimientos por periodo

1. Abre **Cuentas y saldos**.
2. Selecciona **Desde** y **Hasta (incluido)**.
3. Pulsa **Consultar periodo**.

**Resultado:** verás la variación de cargos, pagos netos simulados y número de movimientos dentro de esas fechas, con horario de Perú. Un periodo con devoluciones puede mostrar un flujo negativo. La tabla inferior muestra las últimas 100 solicitudes y sus saldos actuales, independientemente del periodo seleccionado. Los importes históricos se conservan aunque se modifiquen tarifas después.

## Entender los avisos de n8n

Cada cargo inicial, pago, descuento, devolución y contrapartida encola un aviso. El workflow de automatización ya existente los procesa hacia **Mailpit** para el administrador ficticio. No envía dinero ni correos a Internet. Reenviar un movimiento no vuelve a encolar el mismo aviso. El aviso muestra el saldo actual al procesarse, no una fotografía del saldo a la hora original del movimiento.

## Resolver problemas

| Mensaje o síntoma | Motivo | Qué hacer |
| --- | --- | --- |
| No aparece el apartado financiero | La cuenta no tiene permiso financiero | Pide al administrador el permiso correspondiente; no se concede a visitantes. |
| Pago supera el saldo | Se ingresó un importe mayor al pendiente | Revisa el saldo actualizado y corrige el importe. |
| Devolución supera lo pagado | Ya se devolvió o anuló parte del pago | Revisa el historial antes de registrar otro movimiento. |
| La reserva debe estar confirmada | Aún está pendiente o fue cancelada | Resuelve su estado desde Reservas; no se confirma automáticamente por este libro. |
| Anulación dejaría un neto negativo | Hay descuentos o devoluciones posteriores | Corrige primero los movimientos vinculados. |
| No aparece un aviso inmediatamente | El flujo periódico aún no se ejecutó o tiene una cola pendiente | Espera la siguiente ejecución y revisa n8n y Mailpit. |

**Glosario:** *cargo neto* es el cargo inicial más adicionales menos descuentos y sus correcciones; *pagado neto* es lo registrado como pago simulado menos devoluciones y correcciones; *saldo* es cargos netos menos pagado neto; *contrapartida* es una nueva línea que invierte el efecto de un movimiento conservando su historial.

Fuentes: módulo financiero, formularios y pruebas de reglas, concurrencia, navegador y avisos n8n. La integración bancaria y la facturación electrónica pertenecen a fases posteriores.
