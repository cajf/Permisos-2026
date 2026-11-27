# Origen de horas de compensatorio

Bici usuario se oculta del formulario de nuevas solicitudes y del catálogo de duraciones y soportes; la categoría y los expedientes históricos se conservan. El servidor rechaza su radicación independiente, incluso si se envía directamente el identificador antiguo. Las solicitudes históricas siguen consultables y revisables; una devolución de esa categoría debe reconducirse al trámite de acto administrativo, sin convertir automáticamente saldos ni expedientes.

Los nuevos actos requieren seleccionar Tiempo suplementario o Bici usuario. Se guarda en `acts.origin` y queda registrado en el evento de creación. Nómina visualiza la clasificación al revisar. También aparece al seleccionar las horas aprobadas en una solicitud. Las reservas, saldos, vencimientos y aprobación por Nómina conservan las mismas reglas para ambos orígenes. Los actos pueden combinarse según el mecanismo existente.

Los actos anteriores conservan NULL y se muestran como Sin clasificar — histórico. No se presume el origen de los registros previos. El número de resolución sigue siendo único por funcionario, como en el esquema anterior.

## Identificación automática en solicitudes y tableros

El formulario calcula el origen a partir de las horas positivas seleccionadas y lo muestra también en el resumen. El detalle del trámite vuelve a obtenerlo de las asignaciones guardadas: Tiempo suplementario, Bici usuario, Mixta o Sin clasificar. No se acepta un origen declarado por el solicitante diferente de los actos. Mixta significa más de un origen, incluyendo combinaciones de origen conocido e histórico sin clasificar.

La sección de desglose por origen se retiró de los tableros generales de aprobadas y disfrutadas. Se conserva el cálculo de conteos exclusivos por clasificación y horas separadas por origen para el futuro tablero individual. Los filtros de vigencia y dependencia aplican al desglose; una solicitud mixta cuenta una vez. Si se seleccionan todas las vigencias, se conserva el conteo único de cada solicitud. La atribución de horas usa el orden de asignación (`allocations.id`) sobre el tiempo cronológico de la solicitud, de igual forma que los reintegros por cancelación parcial. No se prorratea arbitrariamente. Los años previos se descuentan del tramo de cada acto antes de mostrar el año filtrado. Las horas sin asignación histórica completa se muestran sin clasificar.

La exportación para Power BI incorpora clasificación y segundos por cada origen, manteniendo una fila por solicitud y vigencia. No agrega datos personales. La actualización automática usa el mismo endpoint protegido y el mismo permiso de los tableros existentes.

Antes de desplegar ejecutar con credenciales administrativas `php scripts/migrate-act-origin.php`. La migración agrega una columna nullable y es repetible; no borra datos ni cambia horas. Las instalaciones nuevas ya incluyen la columna en schema.sql. No se debe desplegar el código antes de aplicar la migración.
