# Notificaciones por API de Brevo

## Estado y pasos del usuario

La integración admite envío mediante cuenta gratuita, remitente verificado y clave privada. Su activación depende de las variables de cada entorno. Las claves y los datos de conexión no se incluyen en este documento ni en GitHub.

## Edición de plantillas

El administrador de Talento Humano accede a Administración > Plantillas de correo, o desde Correos > Editar plantillas de correo. Hay siete formularios: registro de solicitud, aprobación del jefe inmediato, aprobación final, devolución, rechazo de solicitud y aprobación o rechazo del acto administrativo por Nómina. Cada uno permite editar asunto (hasta 180 caracteres, una línea) y mensaje (hasta 4.000 caracteres de texto plano), guardar y consultar una vista previa con datos de ejemplo. No se envía ningún correo al guardar ni al consultar la vista previa.

Use {radicado} para solicitudes, {resolucion} para actos y {enlace} para la dirección de la aplicación. Las variables se sustituyen al generar la notificación. Si el mensaje no incluye {enlace}, se agrega al final. El HTML escrito se muestra como texto y no se ejecuta. Los comentarios, motivos privados y soportes no se añaden automáticamente. Las otras notificaciones conservan el mensaje genérico.

Las plantillas se guardan en settings con claves mail_template_*, sin cambios de esquema. La cola outbox conserva una copia del asunto y del mensaje renderizados al producirse el evento; las modificaciones posteriores no cambian mensajes ya encolados. La edición exige rol admin, sesión y CSRF, y queda registrada en Historial. La aprobación final sustituye a la intermedia cuando solo aprueba el jefe inmediato o el alcalde. Las aprobaciones masivas utilizan el mismo flujo y las mismas plantillas que las individuales.

1. Para las pruebas, use un correo propio al que tenga acceso o cree un buzón gratuito dedicado. Identifíquelo como pruebas; no lo presente como buzón oficial de la Alcaldía. Cree el buzón y complete sus términos directamente en el proveedor.
2. Regístrese en https://www.brevo.com/ y elija Free. Complete personalmente verificación de identidad, términos y confirmación de correo. No agregue tarjeta ni compre créditos o complementos.
3. En Remitentes y dominios, agregue ese correo como remitente y confirme el mensaje recibido. Una dirección Gmail/Outlook no permite controlar DNS de su dominio; Brevo puede sustituir el dominio visible por uno de Brevo. Para producción solicite un buzón y la autenticación DNS del dominio institucional.
4. Active correo transaccional si Brevo lo solicita. Si exige un dominio propio o no aprueba el remitente gratuito, detenga la activación e indíquelo; no contrate un plan como solución automática.
5. En SMTP y API > Claves API cree una clave para esta aplicación. No la envíe por chat ni la escriba en GitHub. Guárdela directamente como variable privada en Render. No utilice la contraseña ni una clave SMTP.
6. Configure las variables de la tabla, revise que no exista ninguna prueba pendiente no deseada y conserve APP_MAIL_ENABLED=0 hasta terminar. Activar el envío transmitirá a Brevo las direcciones destinatarias y asuntos de las novedades registradas desde la fecha de inicio.
7. Cuando estén listos el remitente y la clave, indique únicamente el correo remitente y la dirección autorizada para la prueba. Coordinaremos la activación; no comparta la clave. Tras activarlo, un administrador puede registrar una prueba desde Correos y procesar un lote. Debe verificarse la recepción real, incluyendo spam, y el identificador de Brevo.

## Variables privadas

Se leen en local y en Render; ninguna clave se muestra en el formulario ni se guarda en código. Render: servicio > Environment. Local: variables de entorno del proceso antes de abrir iniciar.cmd, o la sección mail de config/local.php (archivo privado excluido de Git).

| Variable | Valor |
|---|---|
| APP_MAIL_ENABLED | 0 mientras se prepara; 1 solo al autorizar la activación |
| APP_BREVO_API_KEY | Clave API privada del proyecto |
| APP_MAIL_FROM | Dirección real registrada y verificada |
| APP_MAIL_FROM_NAME | Nombre visible, identificando pruebas mientras corresponda |
| APP_MAIL_START_AT | Fecha fija de inicio: AAAA-MM-DD HH:MM:SS, hora de Bogotá |
| APP_MAIL_DAILY_LIMIT | 250 por defecto; el código limita a 300 como máximo |
| APP_BASE_URL | URL pública HTTPS de la aplicación en Render; ya existe en esa instalación |

No configure una fecha antigua: el objetivo es excluir avisos acumulados durante las pruebas. El filtro se aplica a created_at de outbox. Los mensajes anteriores permanecen conservados sin enviarse automáticamente. No se activan envíos solo por guardar una clave: también se exigen remitente, fecha y habilitación explícita.

## Funcionamiento

- La API usa HTTPS y validación TLS, endpoint fijo https://api.brevo.com/v3/smtp/email. Las credenciales van en la cabecera privada api-key; no se admiten redirecciones. PHP requiere cURL, incorporado en Docker.
- Se conserva outbox. El reclamo atómico y un bloqueo MySQL evitan que trabajadores simultáneos envíen el mismo registro. No cambia el esquema. settings guarda contadores, pausas e identificadores de aceptación bajo claves mail_*.
- Los siete eventos configurables utilizan la copia de la plantilla guardada en la cola. Las demás notificaciones utilizan el aviso genérico con enlace a la aplicación. No se transmiten los cuerpos antiguos con motivos o comentarios ni anexos; la información completa se consulta dentro de la aplicación.
- Se procesan lotes de cinco cada 30 segundos mientras el trabajador está activo. El botón administrativo procesa hasta tres. No se envía desde el registro/aprobación de una solicitud, por lo que una falla del proveedor no revierte el trámite.
- En Render, el contenedor inicia el trabajador como www-data cuando APP_MAIL_ENABLED=1. El plan gratuito puede suspenderse por inactividad; no se garantiza envío inmediato ni continuidad 24/7. Para producción se necesita ejecución continua autorizada. No se creó ningún servicio de pago ni monitor externo.
- En local, iniciar.cmd inicia el trabajador oculto solo si la configuración es válida y lo detiene al cerrar el servidor. Para operación manual: PHP scripts/mail-worker.php --check verifica configuración sin enviar; sin argumentos procesa un lote; --loop mantiene el trabajador. Use la instalación PHP del proyecto y su php.ini.
- El límite local cuenta intentos por día de Bogotá, incluidos los fallidos, y protege esta instalación. No sustituye el límite compartido de la cuenta de Brevo ni los controles del proveedor. Cuando se alcanza, los pendientes esperan al siguiente día. Cada entorno tiene contador propio; si comparte clave entre local y nube, sus consumos se suman en Brevo. Active solo uno durante la prueba.

## Estados y errores

201 con messageId significa aceptado por Brevo; no confirma entrega en bandeja. El panel muestra el identificador. Las direcciones de ejemplo (.invalid, .test, .example, localhost y example.com/org/net) se bloquean antes de contactar la API.

429 deja reintento pendiente y pausa 15 minutos; hay un máximo de cinco intentos por mensaje. Rechazos de configuración/remitente quedan Bloqueados. Errores de red, 5xx o respuestas ambiguas quedan No confirmados; no se reintentan automáticamente porque el proveedor pudo aceptar el correo. Un trabajador interrumpido deja En proceso; tampoco se reclama de nuevo. Revise Brevo antes de cualquier reposición manual para evitar duplicados. No hay botón de reenvío indiscriminado ni se presupone entrega de una respuesta ambigua.

La compatibilidad SMTP se mantiene únicamente mediante scripts/mail-worker.php --smtp; no se usa automáticamente en la nube. No ejecute ambos transportes como solución al mismo mensaje.

## Pruebas

tests/run-mail.ps1 crea MySQL y un servidor HTTP aislados. Todas las respuestas de envío se simulan; no contacta Brevo. Verifica inactividad por defecto, exclusión histórica, contenido mínimo, direcciones ficticias, aceptación, clasificación de errores, cuota, pausa, concurrencia y permisos de la pantalla. La prueba real queda pendiente del buzón, cuenta, clave y autorización del destinatario.

Referencias: [API transaccional](https://developers.brevo.com/reference/send-transac-email), [remitentes](https://help.brevo.com/hc/en-us/articles/208836149-Create-a-new-sender-From-name-and-From-email), [dominios](https://help.brevo.com/hc/en-us/articles/16045394674066-Troubleshooting-Domain-authentication), [plan gratuito](https://help.brevo.com/hc/en-us/articles/208580669-FAQs-What-are-the-limits-of-the-Free-plan), [limitaciones de Render gratuito](https://render.com/docs/free).
