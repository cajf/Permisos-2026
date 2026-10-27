# Gestión del Talento Humano de Bucaramanga

Aplicación web local en PHP para permisos, compensatorios y licencias de empleados de planta. Incluye aprobación por etapas, excepción de dos horas, aprobación exclusiva del alcalde para permisos de alto rango, saldos por acto, corrección, cancelación autorizada, PDF con anexos, importación de XLSX/CSV, calendario y cola de correo.

## Iniciar en este equipo

1. Abra PowerShell en esta carpeta y ejecute `./iniciar.ps1`.
2. Abra <http://127.0.0.1:8088>.
3. Consulte `storage/acceso-local.txt` para las cuentas de demostración y la contraseña inicial generada.
4. Cambie la contraseña al ingresar. Los funcionarios, dependencias y correos precargados son ficticios.

La ejecución usa PHP aislado en `.runtime/php`. No modifica las instalaciones existentes de XAMPP. Si la política de PowerShell impide ejecutar scripts, use `iniciar.cmd` o los comandos PHP que aparecen abajo.

## Instalar en otro equipo

Se requiere PHP 8.4 o superior compatible con las versiones de `composer.lock` y las extensiones PDO SQLite, fileinfo, mbstring, GD, zip, intl, DOM, XML, curl y OpenSSL. No hace falta Node para ejecutar la aplicación. La base de datos inicial es SQLite y está fuera de la carpeta pública.

```powershell
composer install --no-dev --prefer-dist
php scripts/install.php
php -S 127.0.0.1:8088 -t public public/router.php
```

Si no dispone de Composer global, puede ejecutar el PHAR con PHP. El instalador es idempotente: no reemplaza una base inicializada. Configure `config/local.php` a partir de `config/example.php` para cambiar la ubicación de la base, la URL y SMTP. No incluya el archivo de configuración local ni las credenciales en control de versiones.

El servidor incorporado de PHP se utiliza para la revisión local. La instalación futura en Internet debe usar un servidor web con la raíz documental en `public/`, HTTPS, credenciales institucionales y configuración de sesiones, respaldos y correo acorde con ese entorno. No se ha realizado ese despliegue. La integración con el directorio de Windows permanece fuera de esta primera versión.

## Documentación

- `docs/manual.pdf`: manual de usuario, administración e instalación, con diagramas.
- `docs/manual.html`: versión navegable sin conexión.
- `docs/diagramas/`: diagramas SVG y fuentes Mermaid editables.
- `docs/requerimientos.md`: decisiones funcionales y puntos por completar.
- `database/schema.sql`: esquema y restricciones de integridad.

## Configuración inicial por Talento Humano

1. Cree dependencias y funcionarios o importe el Excel adaptándolo a la plantilla descargable.
2. Configure jefes inmediatos, clasificación de alto rango y responsables institucionales. Los suplentes solo deben registrarse cuando exista designación competente.
3. En **Tipos y soportes**, marque o desmarque **Exigir al menos un soporte** en cada tipo de permiso, compensatorio o licencia y pulse **Guardar regla**. Al desmarcarlo se permite enviar sin adjuntos. La opción es independiente de **Regla revisada por Talento Humano**. Los archivos que se carguen se siguen validando. El documento al registrar un acto administrativo para otorgar saldo continúa siendo obligatorio. Revise también los límites adicionales pendientes de validar; el texto de frecuencia es informativo.
4. Revise el calendario precargado y registre horarios temporales.
5. Configure SMTP y programe el trabajador de correo usando el programador de tareas del servidor:

```powershell
php scripts/mail-worker.php
```

Sin SMTP, las novedades quedan pendientes y no se envían. El trabajador procesa hasta 50 mensajes por ejecución y reintenta los fallidos hasta cinco veces. Un envío que quede en estado `sending` por una interrupción requiere revisar la entrega antes de reponerlo en la cola, para evitar duplicados.

## Verificación

Ejecute `./tests/run.ps1` para crear una base aislada, verificar las reglas, iniciar un servidor de pruebas en el puerto 8089, ejecutar las pruebas HTTP y detener ese servidor al terminar. Mantenga libre el puerto 8089. La base operativa no se modifica.

También puede ejecutar las comprobaciones por separado. `tests/http.php` requiere un servidor de pruebas con la variable `APP_TESTING=1` y la base indicada en `.work/test-result.json`.

```powershell
./.runtime/php/php.exe -c .runtime/php/php.ini tests/core.php
```

El resultado de esa ejecución queda en `.work/test-result.json`. Use su campo `database` como variable de entorno `APP_DATABASE` antes de iniciar otro servidor PHP en 8089. Ejecute después:

```powershell
./.runtime/php/php.exe -c .runtime/php/php.ini tests/http.php
```

Resultado actualizado: 55 verificaciones funcionales y 41 verificaciones HTTP, incluyendo soporte obligatorio y opcional en las cuatro familias, migración de bases existentes, autorización del administrador, PDF con y sin anexos e importación real de XLSX. Las pruebas usan fechas de referencia de 2026 y deben actualizarse al cambiar esa ventana de prueba. No se verificó entrega de correo contra un SMTP institucional.

Las bases existentes reciben automáticamente la columna `supports_required` al abrir una conexión. La actualización es aditiva y conserva todos los tipos como obligatorios hasta que el administrador cambie su configuración. También puede ejecutarse explícitamente con `php scripts/migrate.php`.

## Respaldos y estructura

`php scripts/backup.php` genera una copia consistente de SQLite y de los soportes dentro de `storage/backups/`. Resguarde también `config/local.php` por un canal protegido. La recuperación debe hacerse con el servicio detenido y con base y archivos del mismo respaldo.

La aplicación utiliza PDO con consultas parametrizadas, transacciones `BEGIN IMMEDIATE` para las reservas, contraseñas con `password_hash`, protección CSRF, sesiones HttpOnly/SameSite y comprobación de acceso para cada solicitud, soporte y PDF. No elimina solicitudes ni decisiones históricas desde la interfaz. Los archivos admitidos son PDF, JPG y PNG; los PDF se validan antes de aceptarse y deben ser compatibles con FPDI y estar sin contraseña.

## Referencias técnicas y de calendario

- [PHP para Windows](https://www.php.net/downloads.php?os=windows&version=8.4).
- [FPDI con TCPDF](https://manuals.setasign.com/fpdi-manual/v2/installation/).
- [Calendario nacional y traslados de festivos - Ley 51 de 1983](https://www.cancilleria.gov.co/sites/default/files/Normograma/docs/ley_0051_1983.htm).
- [Circular MTIC 23 de 2026 sobre el festivo nacional del 9 de julio](https://normograma.mintic.gov.co/mintic/compilacion/docs/circular_mintic_0023_2026.htm).

Los soportes y duraciones del catálogo se basan en el formato aportado y en las decisiones del usuario; no representan una validación normativa adicional.
