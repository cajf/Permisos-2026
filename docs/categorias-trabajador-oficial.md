# Cargos y categorías de trabajador oficial

En **Cargos y grados**, Talento Humano registra las categorías desde el formulario **Categorías de trabajador oficial**. Una categoría puede agrupar varios cargos. Para crear un cargo oficial, seleccione **Trabajador oficial** y su categoría; código y grado no aplican. El nombre del cargo es único en todo el catálogo, por lo que no puede existir simultáneamente en ambos grupos.

Las demás vinculaciones mantienen el modelo de cargo con código y uno o varios grados. No es posible agregar grados a un cargo oficial. Si un cargo tiene funcionarios asociados de otro grupo, no se permite cambiar su grupo; debe revisarse primero la clasificación de esas personas. No se crean categorías ficticias ni se infieren nombres durante la actualización.

En **Funcionarios**, seleccione la vinculación: el listado de cargos se filtra por su grupo. Al escoger un cargo oficial se muestra su categoría y se ocultan código y grado. El servidor obtiene la categoría del catálogo e impide enviar un cargo de otro grupo, incluso si se manipula el formulario. La importación Excel/CSV utiliza la columna `vinculacion` con valor `Trabajador oficial` y un cargo oficial previamente registrado; `codigo` y `grado` pueden quedar vacíos. No requiere una columna adicional de categoría.

Renombrar una categoría actualiza la clasificación actual de los funcionarios asociados. Las solicitudes guardan una instantánea de categoría, vinculación y cargo al radicarse. Los cambios posteriores no alteran esas instantáneas; los PDF históricos que utilizaban código y grado conservan su información. Los nuevos PDF de trabajadores oficiales muestran la categoría.

## Base de datos

`official_job_categories(id,name)` contiene nombres únicos de categoría. `jobs.employment_group` identifica `regular` u `official_worker`; `jobs.official_category_id` referencia la categoría mediante clave foránea. Un cargo oficial no utiliza `job_grades` ni código. `users.job_category` conserva la clasificación actual derivada del catálogo, y `requests.snapshot` conserva la clasificación histórica. Las tablas nuevas están incluidas en los respaldos y transferencias.

La actualización se realiza con `scripts/update-official-job-categories.php`, opcionalmente con `--config` para una configuración privada. Antes del cambio crea y verifica un respaldo. La migración de columnas y claves es idempotente; no elimina cargos, grados ni solicitudes existentes.

Los permisos existentes **Ver cargos y grados** y **Gestionar cargos, grados y categorías** controlan esta sección. La edición queda registrada con usuario y fecha en el historial. Las pruebas `tests/official-job-categories.php` verifican relación de una categoría con varios cargos, incompatibilidad entre grupos, importación, validaciones y conservación histórica sin enviar correos reales.
