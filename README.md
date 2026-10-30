# Gestión del Talento Humano de Bucaramanga

Aplicación PHP para permisos, compensatorios y licencias de empleados de planta. Usa **MySQL 8.4 LTS**, InnoDB y utf8mb4. Incluye aprobación por etapas y masiva, designaciones por dependencia y vigencia, cupos y saldos, Nómina, Cumpleaños, correcciones, cancelaciones, PDF con anexos, importación XLSX/CSV y cola de correo.

## Iniciar en este equipo

1. Ejecute `iniciar.cmd` o `./iniciar.ps1` desde el proyecto.
2. Abra <http://127.0.0.1:8088>.
3. Ingrese con sus mismos usuarios y contraseñas. La migración conserva sus identificadores, claves, solicitudes y soportes. El cambio de motor requiere iniciar sesión nuevamente.

`iniciar.cmd` ejecuta `iniciar.ps1`: inicia el MySQL independiente si está detenido, espera su disponibilidad y comprueba la conexión y el esquema con PHP antes de iniciar el portal. Si esa comprobación falla, detiene el arranque y muestra el motivo. MySQL escucha únicamente en `127.0.0.1:3310`; el portal PHP usa el puerto 8088. XAMPP y su MariaDB no se modifican. Los binarios están en `.runtime/php` y `.runtime/mysql-8.4.13-winx64`; los datos y la configuración privada del servidor están en `storage/mysql`.

Para detener únicamente este MySQL, cierre primero el portal y ejecute:

```powershell
./.runtime/php/php.exe -c .runtime/php/php.ini scripts/stop-mysql.php
```

## Configuración y credenciales

`config/example.php` describe host, puerto, base, usuario, contraseña, directorio privado de soportes y SMTP. La configuración efectiva está en `config/local.php`, excluido de Git. El usuario web tiene solo SELECT, INSERT, UPDATE y DELETE en la base del proyecto. Las operaciones de administración usan una conexión separada conservada en `storage/mysql/admin.php` para esta instalación local.

No publique `config/local.php`, `storage/`, `.work/`, `.runtime/`, respaldos o archivos de acceso. El repositorio contiene solo código, pruebas con datos ficticios y documentación. Las credenciales reales no están en comandos de inicio ni en GitHub.

## Instalar en otro equipo

Se requieren PHP 8.4 con PDO MySQL, fileinfo, mbstring, GD, zip, intl, DOM, XML, curl y OpenSSL, y MySQL 8.4 o superior. PDO SQLite se utiliza únicamente para migrar versiones antiguas y ejecutar sus pruebas de compatibilidad. Node no es necesario para ejecutar la aplicación.

```powershell
composer install --no-dev --prefer-dist
```

Para una instancia independiente en Windows, coloque PHP en `.runtime/php` o indique su ejecutable a `configurar-mysql.ps1`. El script descarga el ZIP oficial fijado de MySQL, comprueba su checksum, inicializa datos privados y configura credenciales aleatorias:

```powershell
./configurar-mysql.ps1
```

Si conserva una base SQLite anterior, siga el procedimiento de migración de la sección siguiente. Para una instalación nueva sin datos previos:

```powershell
Copy-Item storage/mysql/connection.php config/local.php
./.runtime/php/php.exe -c .runtime/php/php.ini scripts/install.php
./iniciar.ps1
```

El instalador no reemplaza una base que ya contiene funcionarios. Las contraseñas temporales de una instalación nueva se guardan en `storage/acceso-local.txt` y se deben cambiar al ingresar.

Para un servidor MySQL existente, el administrador crea una base utf8mb4, carga `database/schema.sql` y asigna una cuenta limitada a esa base. Configure `config/local.php` con sus datos y ejecute el instalador solo si es una base nueva. La raíz pública del servidor web es `public/`. La publicación en Internet requerirá HTTPS y configuración institucional; no se ha realizado ese despliegue ni la integración con el directorio de Windows.

## Migrar desde SQLite

La base SQLite original permanece en `storage/app.sqlite` como archivo histórico; la aplicación ya no se conecta a ella. La migración exige un destino MySQL vacío y no sobrescribe datos existentes.

```powershell
./.runtime/php/php.exe -c .runtime/php/php.ini scripts/migrate-sqlite-to-mysql.php --activate
```

También admite `--source=RUTA_SQLITE`, `--source-uploads=CARPETA` y `--config=ARCHIVO_PHP_CONEXION`. Conserva una copia consistente del origen, aplica a esa copia las actualizaciones de versiones antiguas y transfiere las 16 tablas de datos. Verifica conteos y huellas por tabla, referencias y SHA-256 de todos los soportes antes de activar la conexión. Los informes y copias quedan en `storage/mysql`. Si el origen o sus archivos no superan la verificación, revierte la carga y mantiene la configuración previa.

La herramienta no debe ejecutarse con usuarios registrando nuevos datos en el origen. Cierre el servidor web durante el cambio de motor. Una vez activado MySQL, sus nuevas operaciones quedan exclusivamente en MySQL; volver a SQLite requiere un procedimiento explícito de recuperación y no consiste en cambiar una ruta.

## Respaldar y restaurar

```powershell
./.runtime/php/php.exe -c .runtime/php/php.ini scripts/backup.php
```

Cada respaldo contiene `database.sql`, `manifest.json` y `uploads/`. Usa una transacción consistente, verifica los soportes y registra conteos y huellas SHA-256. Resguarde también `config/local.php` por un canal protegido. Evite cambios de esquema mientras se genera un respaldo.

Restaure en una base MySQL vacía, con credenciales que permitan crear tablas y un archivo PHP de conexión separado. No use las credenciales de datos limitadas del portal para administrar el esquema:

```powershell
php scripts/restore-mysql.php --backup=CARPETA_RESPALDO --config=ARCHIVO_PHP_CONEXION --uploads=CARPETA_SOPORTES
```

La herramienta comprueba el SQL, las referencias, conteos y huellas de tablas y archivos. Rechaza bases que ya contengan tablas y soportes incompatibles. Como MySQL confirma operaciones DDL por separado, si una restauración falla debe diagnosticarse y repetirse en otra base vacía; no se elimina ni se reemplaza automáticamente un destino parcial.

## Integridad y transacciones

Las reservas, decisiones y cambios administrativos usan transacciones InnoDB. Un bloqueo `SELECT ... FOR UPDATE` sobre la fila única de `app_locks` conserva la serialización de operaciones de saldo que tenía la versión anterior. Evita carreras entre comprobación y reserva de cupos, actos y beneficios anuales. Las claves foráneas y CHECK se aplican en MySQL, y las contraseñas siguen almacenadas con `password_hash`.

Las pruebas incluyen procesos independientes que intentan simultáneamente consumir el cupo semestral, reservar un mismo saldo, solicitar Cumpleaños y aprobar una misma etapa. La captura de horarios especiales continúa retirada; se usa la jornada laboral configurada y los horarios temporales de Talento Humano.

## Configuración funcional

- En **Funcionarios**, mantenga datos, fecha de nacimiento, roles y clasificación de alto rango.
- En **Designaciones**, asigne jefes y suplentes a una o varias dependencias y al subsecretario institucional por vigencia. Los responsables anteriores conservan sus etapas pendientes; no desactive sus cuentas o roles mientras las tengan.
- En **Consulta de saldos**, los jefes consultan únicamente sus dependencias y periodos designados. El historial por instantáneas comienza con la versión que lo introdujo.
- **Nómina** aprueba o rechaza el registro de actos administrativos y conserva observación, responsable, fecha y hora. No agrega una etapa al disfrute del compensatorio.
- **Cumpleaños** permite una jornada completa por vigencia. Quienes nacen en diciembre pueden disfrutarlo hasta el 30 de enero siguiente con cargo al año anterior. Una cancelación sin disfrute libera el beneficio; el disfrute parcial lo conserva consumido.
- En **Tipos y soportes**, seleccione si cada tipo exige un adjunto. El documento del acto administrativo para otorgar saldo continúa siendo obligatorio.
- En **Por aprobar**, jefes y subsecretario pueden aprobar hasta 100 etapas seleccionadas por acción, con resultados parciales y sin saltarse etapas.

## Plazo, calendario y correo

El plazo es de **48 horas calendario dentro de días hábiles**: cuenta noches y almuerzos de lunes a viernes, y excluye completos sábados, domingos y festivos. Se conserva la excepción con justificación. La duración usa la jornada laboral y el cupo ordinario sigue siendo de 25 h 30 min por semestre, incluidas solicitudes de hasta dos horas.

La jornada base es de lunes a jueves 07:30–12:00 y 13:00–17:00, y viernes 07:00–12:00 y 12:30–16:00. Talento Humano administra festivos y horarios temporales. El calendario nacional se precarga para el año de instalación y los dos siguientes.

Configure SMTP en `config/local.php` y programe `php scripts/mail-worker.php`. Sin SMTP los mensajes permanecen pendientes. Cada ejecución procesa hasta 50 mensajes y reintenta los fallidos hasta cinco veces. No se ha verificado entrega institucional. Un mensaje que quede en `sending` requiere revisar la entrega antes de reponerlo.

## Pruebas y documentación

`./tests/run.ps1` crea una base y un usuario MySQL aislados, ejecuta las reglas y pruebas de concurrencia, inicia un servidor HTTP temporal en 8089 y lo detiene al terminar. La base operativa no se modifica. Para crear esas bases se necesita la configuración administrativa local de `storage/mysql/admin.php`. Los resultados están en `.work/test-result.json` y `.work/http-result.json`; la configuración privada de la prueba queda en `.work`.

Las fechas de prueba de referencia son de 2026 y deben actualizarse al cambiar esa ventana. Las pruebas de migraciones antiguas usan SQLite únicamente como origen. También se verifica una transferencia a MySQL y una restauración real a otra base vacía.

`php tests/startup.php` comprueba el acceso MySQL disponible, el bloqueo del arranque ante una conexión rechazada y la respuesta HTTP 503 sin errores internos expuestos. Se incluye también en `tests/run.ps1`. Si inicia PHP directamente sin pasar por `iniciar.cmd` o `iniciar.ps1`, debe iniciar MySQL antes.

- `docs/manual.pdf` y `docs/manual.html`: guía de uso, administración e instalación.
- `docs/diagramas/`: SVG y fuentes Mermaid.
- `docs/requerimientos.md`: decisiones funcionales confirmadas.
- `database/schema.sql`: esquema operativo MySQL.
- `database/legacy/sqlite-schema.sql`: compatibilidad exclusiva con la migración anterior.
- `php scripts/export-schema.php`: exporta solo metadatos MySQL para el diccionario y diagramas.

## Demostración en Internet y restauración

El proyecto incluye un `Dockerfile` para PHP 8.4/Apache y configuración de MySQL remoto con TLS y certificado verificado. En modo nube los soportes originales se guardan en MySQL; la copia de disco se recupera automáticamente si el alojamiento reinicia el contenedor. La instalación local conserva su almacenamiento en disco. Consulte [docs/publicacion.md](docs/publicacion.md) para Render Free y Aiven MySQL Free, sus variables privadas y límites. No se utiliza MariaDB.

`php scripts/restore-original-demo.php --confirm=RESTORE_ORIGINAL_DEMO` crea un respaldo y restaura los seis usuarios ficticios de la primera versión, tres dependencias y 17 tipos, sin trámites. Genera claves nuevas en un archivo privado de `storage`, invalida las sesiones anteriores y retira los soportes respaldados. No se usa como comando de arranque ni de despliegue.

`tests/original-demo.php` y `tests/attachment-storage.php` comprueban la restauración y la recuperación de soportes en bases aisladas. Ejecútelos con `-d zend.assertions=1 -d assert.exception=1`. La variable de pruebas `APP_TEST_ATTACHMENT_STORAGE=mysql` permite ejecutar `tests/run.ps1` con el almacenamiento de soportes que utiliza la nube.

## Referencias técnicas

- [MySQL Community 8.4 para Windows](https://dev.mysql.com/downloads/mysql/8.4.html?os=3).
- [Bloqueos de lectura InnoDB](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).
- [Modelo de transacciones InnoDB](https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-model.html).
- [Métodos de respaldo MySQL](https://dev.mysql.com/doc/refman/8.4/en/backup-methods.html).

Los soportes y duraciones del catálogo proceden del formato suministrado y las decisiones del usuario; no representan una validación normativa adicional.


## Catálogo de cargos y revisión masiva

Talento Humano registra los cargos con su código y grados desde **Cargos y grados**. Funcionarios ofrece las listas relacionadas y carga el código automáticamente. Registre antes en el catálogo las combinaciones que importará desde Excel. Los datos históricos de las solicitudes se conservan.

Para actualizar una instalación existente, ejecute `php scripts/update-job-catalog.php` con acceso administrativo a MySQL. La herramienta crea un respaldo verificado, agrega las tablas y traslada los cargos actuales sin inventar datos faltantes. No requiere ampliar los privilegios del usuario web. Consulte [las instrucciones](docs/cargos-y-aprobaciones.md).

La aprobación individual y la masiva comparten las validaciones. Cada solicitud del lote se procesa en su propia transacción; si falla, se muestra el motivo en su fila y continúa el resto del lote. Se admite el registro urgente y la aprobación excepcional durante el disfrute con justificación. Cada aprobador debe explicar su motivo; en lotes se exige por solicitud. No se registran ni aprueban periodos completamente terminados. Se conserva toda la duración original, con trazabilidad en historial y PDF.

## Certificado electoral para Sufragar

El OCR local de Tesseract contrasta cédula y fecha electoral y exige disfrute entre los días 1 y 30 calendario siguientes. Las inconsistencias legibles bloquean el registro; la falta de lectura permite radicar con alerta destacada y revisión manual obligatoria por cada aprobador, también en lotes. El resultado se conserva en la copia histórica de la solicitud y en el PDF, sin agregar tablas. Consulte [configuración, límites y pruebas](docs/certificado-electoral.md). El Dockerfile incorpora las dependencias de OCR y PDF para la nube.

El compensatorio Claveros / Jurado exige elegir modalidad y una jornada completa. Para jurado se reconoce la constancia de prestación del servicio y se aplica el plazo de 45 días hábiles según el calendario registrado. La lectura no concluyente exige revisión manual individual o masiva. Clavero no utiliza este OCR.

## Tablero de disfrute

El subsecretario titular vigente dispone de un tablero con actualización cada 30 segundos, filtros por vigencia y dependencia, totales únicos y comparación histórica. Incluye disfrute iniciado aprobado y cancelaciones con disfrute parcial, sin sumar tiempo futuro. La exportación privada y los archivos de Power Query y DAX preparan la integración posterior con Power BI, todavía sin cuenta ni informe publicado. Consulte [criterios de conteo y preparación](docs/powerbi/README.md).

## Consulta de saldos

La pantalla inicia sin resultados. Al pulsar Consultar saldos filtra en MySQL por intervalo de fechas, nombres o apellidos (sin distinguir tildes ni mayúsculas) y dependencia, con alcance de acceso y páginas de 10 funcionarios. Presenta el último registro de saldo de cada funcionario dentro del intervalo; no recalcula consumos del periodo. Las pruebas aisladas están en tests/balance-search-run.php.
