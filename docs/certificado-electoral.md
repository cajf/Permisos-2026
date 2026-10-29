# Validación OCR del compensatorio por sufragar

El tipo **Sufragar** lee los soportes en el propio servidor con Tesseract en español. Los PDF se convierten a imágenes con Poppler, por lo que también se procesan escaneos sin capa de texto. No se envían documentos a un proveedor externo.

## Resultado del registro

- Si se reconoce el certificado electoral colombiano, una cédula inequívoca y la fecha de elecciones, se comparan con el documento del solicitante y todas las fechas de disfrute.
- El intervalo permitido comienza el día siguiente a las elecciones y termina el día 30 calendario, inclusive. Por ejemplo, para elecciones del 25 de octubre, del 26 de octubre al 24 de noviembre.
- Una cédula legible que no coincida o una fecha de disfrute fuera de ese intervalo impiden radicar. Una fecha electoral futura detectada también exige corregir el soporte. El formulario conserva los adjuntos seleccionados cuando JavaScript está habilitado y la página permanece abierta.
- Si el motor falla, falta un archivo, la imagen no es legible, los datos son ambiguos o el documento no puede reconocerse, se registra la solicitud con **REVISIÓN MANUAL OBLIGATORIA DEL CERTIFICADO ELECTORAL**.

La alerta aparece en la bandeja, listados y detalle, y se incorpora al PDF. Cada aprobador debe dejar su propia constancia de revisión de cédula, fecha electoral y plazo antes de aprobar. En la aprobación masiva se exige por solicitud; la falta de esa constancia falla únicamente ese registro y el resto del lote continúa. Devolver o rechazar conserva el uso de la observación habitual.

## Alcance de la lectura

Solo se toman campos asociados a etiquetas de cédula y elecciones, con confianza OCR de al menos 80 por palabra de la línea. No se corrigen dígitos por suposición, ni se toma la fecha de expedición como fecha electoral. Se admiten fechas numéricas día mes año y fechas con el mes en español. Los formatos manuscritos, rotados o diferentes pueden requerir revisión manual.

Se limita la lectura a tres páginas por PDF, seis páginas en total y 35 segundos por envío. Un documento que supere estos límites pasa a revisión manual. Estos límites son del análisis automático; no cambian los límites generales de carga. Los archivos temporales de OCR se eliminan después de la lectura.

La lectura no comprueba autenticidad, participación electoral ante la Registraduría ni uso previo del beneficio con el mismo certificado. No sustituye el examen del soporte por los responsables. No se agregaron restricciones de duración ni de reutilización que no se hubieran solicitado.

## Datos y trazabilidad

El resultado se conserva en `requests.snapshot.electoral_check`, junto a la revisión del trámite: estado, versión del analizador, momento de lectura, campos extraídos y huellas SHA 256 de los soportes. No se guarda el texto OCR completo. La corrección vuelve a analizar soportes existentes y nuevos y reemplaza el resultado de la revisión vigente; los eventos anteriores conservan el resultado resumido y las decisiones realizadas.

No hace falta modificar tablas ni ejecutar una migración de datos. Las solicitudes anteriores de Sufragar sin resultado OCR se muestran como pendientes de revisión manual. Al aprobar individualmente o por lote se vuelven a contrastar las fechas con el resultado guardado y la existencia de los soportes usados.

## Dependencias de ejecución

El Dockerfile instala `tesseract-ocr`, `tesseract-ocr-spa` y `poppler-utils`. Se necesita reconstruir la imagen para habilitar OCR en Internet.

En esta instalación Windows el motor está en `.runtime/tesseract` y el conversor en `.runtime/poppler`. Para otro equipo, instale Tesseract con `spa.traineddata` y Poppler. Configure, si procede, las rutas absolutas en `electoral_ocr.tesseract`, `electoral_ocr.pdftoppm` y `electoral_ocr.pdfinfo` de `config/local.php`. Las rutas vacías usan `.runtime` en Windows y PATH en Linux. PHP necesita permitir `proc_open`; si no está disponible, se activa revisión manual.

Fuentes del reconocimiento: [Tesseract](https://tesseract-ocr.github.io/tessdoc/Command-Line-Usage.html), [modelo español oficial](https://github.com/tesseract-ocr/tessdata_fast), [descripción del certificado por la Registraduría](https://www.registraduria.gov.co/Que-es-el-Certificado-Electoral.html). La regla de 30 días corresponde al requerimiento funcional configurado para este proyecto.

## Verificación

`tests/electoral.php`, incluido en `tests/core.php`, usa datos ficticios en MySQL aislado. Comprueba lectura real de una imagen y un PDF escaneado, límites de fecha, cédula distinta, ausencia del motor, persistencia del aviso y revisión manual por ambos aprobadores, incluida una aprobación masiva con resultados parciales. El ejemplo no es un certificado válido.
