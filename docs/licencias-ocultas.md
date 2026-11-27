# Licencias ocultas de la operación

Se ocultan Actividades deportivas (`deportes`), Para adelantar estudios (`estudios`) y No Remunerada Ordinaria (`no_remunerada`). Las categorías, solicitudes, anexos, estados e historial permanecen en la base de datos. No se ejecuta migración ni eliminación de datos.

La regla de visibilidad se centraliza en app/CategoryVisibility.php. Se aplica a selección de nuevos tipos, duraciones y soportes, Mi espacio y sus conteos, Mis solicitudes, bandejas de aprobación y cancelación, reportes, exportaciones CSV, tableros aprobados/disfrutados y exportación para Power BI, e Historias Laborales. Los saldos históricos conservan su contenido original pero omiten las tres filas al presentarlos.

El detalle, PDF y descarga de soportes de esas solicitudes se deniegan incluso por enlace directo. El registro, reenvío, revisión y cancelación directa también se bloquean. Luto y Matrimonio y sus legalizaciones continúan disponibles. Bici usuario sigue su regla independiente como origen de actos administrativos.

Ocultar no equivale a anular: las solicitudes históricas conservan su estado y sus efectos sobre validaciones de cruces; no se liberan fechas ni se modifica el expediente. Los respaldos completos y el historial de auditoría se conservan sin reescribirlos. La ocultación de la operación no es una eliminación documental.
