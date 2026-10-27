# Requerimientos de la aplicación de permisos de la Alcaldía de Bucaramanga

Documento de trabajo basado en las respuestas del usuario. Los puntos pendientes no constituyen reglas aprobadas. El formato institucional es una referencia documental; las decisiones expresas del usuario prevalecen cuando hay diferencias.

## Objetivo y alcance

Desarrollar una aplicación web en PHP para gestionar permisos, compensatorios y licencias de todos los empleados de planta de la Alcaldía municipal de Bucaramanga. Incluye funcionarios de carrera administrativa, libre nombramiento y remoción, provisionales y trabajadores oficiales.

La primera instalación será local. Se prevé una instalación futura en servidores de la Alcaldía con acceso por Internet. Inicialmente se usarán usuarios y contraseñas de la aplicación; la integración con el directorio de Windows será futura. Existe un listado de funcionarios, dependencias y jefes inmediatos en Excel, aún no entregado.

## Roles confirmados

- Solicitante: registra solicitudes, adjunta soportes y registra actos administrativos que le otorgan horas de compensatorio.
- Jefe inmediato: realiza la primera aprobación.
- Subsecretario administrativo del talento humano: realiza la aprobación final del flujo ordinario y autoriza la cancelación de solicitudes ya aprobadas. Es la misma persona que el jefe de Talento Humano.
- Administrador de Talento Humano: mantiene funcionarios y designaciones por vigencia.
- Alcalde: aprueba exclusivamente los permisos de Asesores de Despacho, Secretarios de Despacho y Jefes de Oficina. Para esos cargos, los compensatorios y licencias siguen el flujo general de jefe inmediato y subsecretario.

Nómina aprueba o rechaza exclusivamente el cargue de actos administrativos de compensatorio, dejando responsable, fecha, hora y observación. No se agrega una etapa de Nómina al disfrute de permisos, compensatorios o licencias.

## Catálogo confirmado

### Permisos

- Ordinario-Remunerado.
- Académico.
- Bienestar, equivalente a Uso de tarjeta vacacional.
- Para ejercer la docencia universitaria.
- Receso Escolar.
- Calamidad.
- Quinquenio, únicamente en la categoría permisos.

### Compensatorios

- Disfrute por acto administrativo.
- Bici usuario.
- Sufragar.
- Claveros / Jurado de votación.
- Cumpleaños.

### Licencias remuneradas

- Matrimonio.
- Luto.
- Actividades deportivas.

### Licencias no remuneradas

- Para adelantar estudios.
- No Remunerada Ordinaria.

## Aprobaciones y ciclo de la solicitud

El flujo general exige primero la aprobación del jefe inmediato y luego la del subsecretario. Aplica a permisos, compensatorios y ambas clases de licencias.

La excepción confirmada es el permiso Ordinario-Remunerado de duración total menor o igual a dos horas: únicamente lo aprueba el jefe inmediato en el flujo general. Para los cargos de alto rango prevalece la aprobación exclusiva del alcalde. La nota general del formato sobre permisos cortos no extiende esta excepción a otros tipos.

Se puede devolver una solicitud para corrección, aprobarla o rechazarla definitivamente. Se puede solicitar la cancelación de una solicitud ya aprobada; la cancelación requiere autorización del subsecretario. Las condiciones de modificación, reenvío y cancelación antes de la aprobación final están pendientes de precisar.

El jefe inmediato y el subsecretario pueden aprobar solicitudes de manera masiva desde su bandeja, con selección individual o de todas las filas de la página. Cada acción admite hasta 100 etapas asignadas, una por solicitud. Cada aprobación conserva su validación, constancia y notificación. Las solicitudes que no puedan aprobarse se informan por separado y no impiden procesar las demás. Las cancelaciones mantienen su decisión individual.

Las aprobaciones deben dejar constancia del usuario, fecha y hora. No se requiere firma digital. Las novedades se comunicarán por correo electrónico.

## Fechas y tiempos

Las solicitudes admiten horas y días completos. La jornada laboral diaria es de 8,5 horas, equivalentes a 510 minutos. No se labora sábados, domingos ni festivos.

Para Ordinario-Remunerado se confirma un máximo de tres días hábiles por semestre del año en curso, equivalentes a 1.530 minutos o 25,5 horas. Todos los permisos por horas, incluidos los de hasta dos horas, consumen ese cupo.

La solicitud debe presentarse con al menos 48 horas calendario dentro de días hábiles, equivalentes a 2.880 minutos. Para este plazo cuentan las 24 horas de cada día hábil, incluidos almuerzos y horas fuera de la jornada. Se excluyen sábados, domingos y festivos completos, según la zona horaria de Bogotá. Los horarios temporales no limitan las horas del plazo. Se permite enviarla fuera del plazo justificando la excepción. La jornada de lunes a jueves es de 07:30 a 12:00 y de 13:00 a 17:00; los viernes, de 07:00 a 12:00 y de 12:30 a 16:00. Deben admitirse horarios temporales con vigencia. Los tiempos se distribuyen por semestre según cada fecha solicitada. La versión inicial admite fechas futuras; el tratamiento está documentado en el manual.

## Actos administrativos y saldos

Para Disfrute por acto administrativo, el solicitante registra el acto e indica el total de horas de disfrute otorgadas. Únicamente un usuario con rol de Nómina, diferente del titular, puede aprobar o rechazar ese registro.

El tiempo solicitado se reserva mientras se aprueba la solicitud, se descuenta definitivamente cuando se obtiene la aprobación final y se libera si se rechaza o cancela. La cancelación de una solicitud aprobada solo se hace efectiva cuando la autoriza el subsecretario.

Los actos tienen fecha de vencimiento. Si una cancelación se autoriza cuando el periodo ya comenzó, se reintegran solo las horas pendientes de disfrutar. La aplicación admite distribuir una solicitud entre varios actos aprobados, en concordancia con los espacios del formato. Cada acto debe cubrir todas las fechas solicitadas.

## Campos y soportes

Talento Humano puede seleccionar, por cada tipo de permiso, compensatorio y licencia, si se exige al menos un soporte. Los tipos existentes conservan por defecto la obligatoriedad. Al marcarlo opcional, se admite enviar y corregir solicitudes sin adjuntos; si se cargan archivos, se mantienen las validaciones de formato y tamaño. Esta opción es independiente de la validación de los límites de duración. El registro del documento de un acto administrativo para otorgar saldo conserva su soporte obligatorio.

El formato institucional aporta los siguientes campos de referencia:

- Fecha de solicitud.
- Nombre y apellidos, cédula, cargo, código, grado y dependencia.
- Varias fechas y horarios por solicitud, con totales de días y horas.
- Tipo de solicitud, justificación y observaciones.
- Parentesco en licencia por luto.
- Número de resolución, horas reconocidas, solicitadas y pendientes; hay espacio para dos resoluciones.
- Identidad del jefe inmediato, cargo y constancia de aprobación.
- Constancia de autorización del subsecretario.

Los espacios de firma se adaptarán a constancias de las aprobaciones. Nómina participa únicamente en la aprobación del registro de actos administrativos, de acuerdo con la modificación confirmada por el usuario.

El formato menciona soportes académicos, acreditación de docencia universitaria, escolaridad de hijos, registro de bicicleta, certificado electoral, certificación de jurado o clavero, identidad, documentos de matrimonio y parentesco, solicitud para actividades deportivas y matrícula y duración de estudios. No se consideran una matriz normativa verificada ni completa.

## Salida documental y comunicaciones

La aplicación generará un PDF con los datos, soportes y constancias de aprobación. Se confirmó la incorporación de los anexos y la recepción de soportes PDF, JPG y PNG.

Las novedades se notificarán por correo electrónico. Aún no se ha suministrado configuración SMTP.

## Entregables solicitados

- Aplicación web en PHP con diseño adaptable.
- Base de datos y procedimiento de instalación local.
- Manual de la aplicación.
- Diagramas de casos de uso.
- Diagramas de la base de datos.

## Complementos pendientes para la operación institucional

1. Designación de responsables competentes para las solicitudes propias de aprobadores; se impide autoaprobar y se admite configurar suplentes designados.
2. Validación de la matriz de duraciones, frecuencias, parentescos y soportes de los tipos que aún no tienen regla confirmada. Se implementó una propuesta editable.
3. Entrega del Excel real, configuración SMTP y aplicación de los recursos gráficos institucionales cuando sean suministrados.

## Referencia documental

Formato F-GAT-8100-238,37-183 SOLICITUD DE PERMISO LICENCIA COMPENSATORIO 03032026.docx, entregado por el usuario. El encabezado indica versión 6.0; el control de cambios contiene una entrada de esa versión con fecha 26/03/2026. Las referencias legales y duraciones presentes en ese archivo no se tratarán como reglas vigentes adicionales sin confirmación.

## Entorno observado

Se implementó la aplicación PHP con SQLite. Se localizaron instalaciones antiguas de PHP en XAMPP y se preparó un runtime independiente en .runtime/php con PHP 8.4.27. Las dependencias están fijadas en composer.lock. La instalación local se realiza desde public y las pruebas usan bases aisladas.


## Designaciones por dependencia y vigencia

Talento Humano asigna un jefe y suplente a una o varias dependencias, sin editar el jefe de cada funcionario. Registra también al subsecretario y su suplente institucional. Cada nueva designación comienza hoy o en el futuro y cierra la anterior el día calendario previo; se conserva usuario, fecha y hora del registro. No se admite aprobar antes de comenzar la designación.

Los responsables anteriores terminan las etapas de los trámites que recibieron antes del relevo, aunque concluya su designación. La cuenta y el rol se conservan mientras haya trámites pendientes. Los reenvíos de solicitudes devueltas usan la designación vigente al reenviar. Las cancelaciones conservan al subsecretario que las recibió.

La consulta de saldos de un jefe anterior se limita a fechas de su periodo y sus dependencias. Se conservan instantáneas inmutables desde la actualización; no se fabrican saldos de fechas anteriores. La configuración individual anterior sirve solo durante la transición hasta el comienzo de la primera designación de cada dependencia.

## Cumpleaños

Permanece como compensatorio. Se disfruta una única jornada completa por vigencia, en cualquier fecha de ese año. Quienes nacen en diciembre pueden trasladarlo al 1-30 de enero siguiente y cargarlo a la vigencia anterior sin consumir el beneficio del año nuevo. Se registra la fecha de nacimiento por Talento Humano. Las solicitudes pendientes, devueltas, aprobadas o en cancelación reservan o consumen el beneficio; el rechazo o la cancelación previa al disfrute lo libera. Si ya hubo disfrute parcial, no se recupera el beneficio anual.

## Jornada laboral aplicable

Se retiró la declaración de horarios especiales en las solicitudes. Los nuevos trámites y las correcciones usan únicamente la jornada laboral configurada, incluidos los horarios temporales administrados por Talento Humano. No se admiten solicitudes en sábados, domingos o festivos ni intervalos que crucen medianoche. La anticipación conserva la regla de 48 horas calendario dentro de días hábiles.

El retiro conserva las solicitudes y aprobaciones existentes sin recalcular sus fechas ni sus minutos. Antes de eliminar las columnas exclusivas de la función retirada, la migración resguarda sus metadatos en la copia histórica de la solicitud. Las correcciones conservan ese archivo, pero sus nuevas fechas se validan con la jornada configurada.
