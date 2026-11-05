# Permisos por rol

El superadministrador abre **Administración > Permisos por rol**. Selecciona uno de los seis roles existentes, consulta la matriz, marca o desmarca permisos y guarda. No se crean roles personalizados. El rol de superadministrador tiene acceso completo, no admite restricciones y es el único que puede editar la matriz.

La matriz distingue vistas, acciones, recursos descargables y alcance de datos. Por ejemplo, habilitar **Cargos y grados** en Vistas permite consultar el catálogo; **Gestionar cargos y grados** permite modificarlo. Para aprobar se necesitan los permisos de revisión y aprobación, además de la designación correspondiente. La aprobación masiva exige además su permiso específico. Cada solicitud conserva sus validaciones de fechas, saldos, soportes y prohibición de autoaprobación.

Cuando una persona tiene varios roles, se aplica la unión de sus permisos. Funcionario corresponde a usuarios sin roles adicionales. Los valores iniciales conservan los accesos anteriores; el superadministrador puede modificarlos. Los cambios se aplican desde la siguiente petición, sin cerrar sesión; no cambian actuaciones realizadas ni reasignan pendientes. Las designaciones siguen determinando quién puede aprobar.

Las cuentas de superadministrador solo pueden ser modificadas o restablecidas por otro superadministrador. No se puede retirar o desactivar al último superadministrador activo. Los demás administradores no pueden asignarse este rol desde formularios o importaciones. La configuración detecta guardados concurrentes y deja en el historial el usuario, fecha, hora y permisos agregados o retirados.

## Almacenamiento y mantenimiento

No se requiere modificar el esquema MySQL. La tabla `settings` conserva cada matriz en `role_permissions_<rol>` y la versión en `role_permissions_version`; `users.roles` conserva la pertenencia a roles y `events` registra los cambios. No hay cambios de contraseñas ni eliminación de datos al actualizar.

La asignación inicial se realiza explícitamente mediante `scripts/assign-superadmin.php --username=admin`, con acceso de mantenimiento a la base correspondiente. No se otorga automáticamente por el nombre de usuario al iniciar sesión. Una vez existe un superadministrador, la utilidad impide crear otro por esta vía; las nuevas asignaciones se realizan desde Funcionarios por un superadministrador.

Los controles se verifican en rutas GET/POST y en servicios de escritura. Las vistas ocultan enlaces, formularios y botones no autorizados. Ocultar un menú no sustituye la autorización del servidor. Los permisos desconocidos se deniegan. Los recursos siguen verificando acceso al registro específico.

Pruebas: `tests/run-permissions.ps1` utiliza una base y un servidor HTTP aislados. Incluye denegaciones por URL/POST, CSRF, concurrencia, unión de roles, revocación de aprobación, autoaprobación y protección del último superadministrador. Las pruebas de correo simulan el proveedor y no envían mensajes reales.
