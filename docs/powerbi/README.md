# Tablero de disfrute y preparación para Power BI

## Entrega actual

El tablero propio está en `/?page=analytics`. Consulta MySQL en cada actualización y se renueva cada 30 segundos mientras la pestaña está visible; al regresar a ella vuelve a consultar. Muestra fecha y hora de última actualización y conserva los últimos datos si hay una interrupción. No es un informe de Microsoft Power BI.

Solo el titular vigente de la designación institucional de subsecretario puede acceder. Sin una designación vigente, se usa el responsable institucional heredado. También se comprueban cuenta activa y rol actual. Administradores, alcalde, jefes, suplentes y subsecretarios anteriores no obtienen acceso por esos roles. La autorización se repite en página, JSON y CSV, no solo en el menú. Respuestas privadas sin caché.

Se incluyen estados aprobada, cancelación pendiente y cancelada, siempre con la etapa final de la revisión aprobada. Se cuenta solo tiempo efectivamente transcurrido de los intervalos laborables conservados en `request_slots.segments`, excluidos los descansos. Una cancelación corta el disfrute en su fecha efectiva; si no tiene esa fecha, no se inventa un consumo histórico. Una cancelación de un trámite sin aprobación final no cuenta.

Cada solicitud cuenta una vez por vigencia en la que tuvo disfrute. En una selección de varias vigencias, las tarjetas y la tabla por dependencia cuentan solicitudes distintas. Por eso, sumar los años puede superar el total único. La comparación histórica mantiene todas las vigencias para la dependencia filtrada. La dependencia procede de `requests.department_id`, conservada al radicar; no se usa la dependencia actual del funcionario. Cuando ese dato histórico falta se agrupa como Sin dependencia histórica. Licencias agrupa remuneradas y no remuneradas.

Los datos son exclusivamente los trámites registrados en esta instalación: no se inventan vigencias ni solicitudes anteriores a la aplicación. Una vigencia sin registros no significa que no hayan existido trámites por fuera del sistema.

## Preparación para Power BI

No hay cuenta o espacio de trabajo de Power BI disponible, por lo que no se publicó un informe en Microsoft ni se generó un PBIX validado. Se entregan la exportación privada del tablero, una consulta Power Query `Disfrute.pq` y medidas `Medidas.dax` para construir el informe posteriormente.

1. Como subsecretario, seleccione Todas las vigencias y Todas las dependencias y exporte el CSV. Guárdelo en una ubicación institucional privada.
2. En Power BI Desktop, cree una consulta en blanco, pegue `Disfrute.pq`, configure su ruta y nombre la consulta `Disfrute`.
3. Cree las medidas del archivo DAX, una por una. No utilice el conteo simple de filas para el total global: una solicitud puede ocupar varias vigencias.
4. Agregue segmentadores por `vigencia` y `dependencia`; tarjetas de total, permisos, compensatorios y licencias; matriz con dependencia en filas y las tres medidas; gráfico de columnas por vigencia con esas medidas como valores. Las cuatro familias originales permiten también separar las licencias.
5. Configure publicación privada en un espacio de trabajo institucional y conceda lectura solo al subsecretario que corresponda. La autorización de la aplicación no reemplaza la de Power BI, y ambas deben actualizarse al cambiar el titular.

La importación CSV es una fotografía al exportar y solo cambia al sustituir el archivo y actualizar el modelo. No equivale a conexión en tiempo real. Si se adopta MySQL como fuente futura, el conector oficial de Power Query admite Import; deben evaluarse actualización, conectividad, puerta de enlace y licencias. No se habilitaron credenciales, enlaces públicos, tokens ni acceso externo a la base.

La futura incrustación debe usar un informe privado y autenticación de Microsoft, o una arquitectura Embedded aprobada por la institución. No use Publicar en web para estos datos. Esta entrega no incluye licencias ni una capacidad de Microsoft.

Referencias oficiales: [incrustación segura](https://learn.microsoft.com/en-us/power-bi/collaborate-share/service-embed-secure), [conector MySQL y modo Import](https://learn.microsoft.com/en-us/power-query/connectors/mysql-database).

## Verificación

`tests/run-analytics.ps1` crea una base MySQL aislada y un servidor temporal en 8091. Comprueba cálculos con hora controlada, cancelaciones, vigencias, dependencia histórica y acceso de cada rol a página, JSON y CSV. No cambia los registros reales. También forma parte de `tests/run.ps1`.
