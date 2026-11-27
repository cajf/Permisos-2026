# Legalización de licencias

La aprobación final de Luto y Matrimonio agrega al correo una instrucción fija de legalización ante Talento Humano, además de la plantilla personalizada. Luto informa treinta días hábiles desde el suceso; no se solicita la fecha del suceso ni se calcula vencimiento. Matrimonio no indica plazo máximo. Las aprobaciones intermedias y otros tipos no reciben ese aviso. No se reenvían correos anteriores.

En el detalle de una licencia aprobada se muestra el aviso y el estado documental independiente Pendiente de legalizar o Legalizada. La cancelación pendiente conserva la aprobación y permite registrar la recepción. Las licencias canceladas conservan la marcación histórica pero no pueden modificarse. Una licencia disfrutada totalmente sí puede legalizarse; no se altera su estado, fechas, cupos ni saldos.

Cuando la marcación está activa, el aviso de obligación se oculta y se reemplaza por una confirmación de legalización con responsable, fecha y hora. Al desmarcarla, reaparecen el aviso y el estado Pendiente de legalizar. El correo ya enviado al aprobar permanece sin cambios.

La sección Legalización de licencias muestra diez resultados por página y filtros por nombre o cédula, tipo y marcación. Por defecto presenta las pendientes. Talento Humano autorizado marca/desmarca un checkbox y guarda. No hay soportes adicionales, motivo obligatorio ni correo por el cambio de marcación. Cada cambio real genera evento con responsable, fecha y hora; guardar sin cambios no duplica el historial. Un control de versión evita sobrescribir una modificación concurrente.

Permisos: `view.legalizations` y `request.legalize`, asignados por defecto al rol admin y disponibles siempre al superadministrador. Además se comprueba rol admin/superadmin y cuenta activa en el servidor. Si el rol tiene una matriz personalizada, el superadministrador debe habilitar ambos permisos; no se sobrescribe su configuración anterior.

Despliegue: ejecutar `php scripts/migrate-legalizations.php` con credenciales administrativas antes de publicar el código. Crea request_legalizations sin cambiar los registros existentes. La ausencia de una marcación significa Pendiente de legalizar. Se conserva el historial de eventos de las marcaciones y desmarcaciones.
