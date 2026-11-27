# OCR electoral en dos etapas

Tesseract se ejecuta primero. Cuando no identifica inequívocamente tipo, cédula y fecha, se ejecuta el proceso Python local `scripts/ocr/read.py`, con detección PP-OCRv4 y reconocimiento PP-OCRv5. No se consulta una API externa. Los valores contradictorios entre etapas quedan para revisión manual; no se seleccionan usando la cédula de la cuenta.

El mensaje temporal muestra texto observado incluso cuando no es válido. Se desactiva con `ElectoralCertificate::SHOW_TEMPORARY_DETAILS=false`. Los informes ya guardados no se releen automáticamente.

## Instalación local Windows

Crear un entorno virtual con Python 3.12 en `.runtime/ocr`, instalar `scripts/ocr/requirements.txt` y ejecutar `scripts/ocr/download_models.py .runtime/ocr/models`. Los ejecutables y pesos no se incluyen en GitHub. Las rutas predeterminadas se pueden sobrescribir en `config/local.php`, dentro de `electoral_ocr`, mediante `python` y `models`.

## Docker y Linux

La imagen instala Python 3.12 y dependencias en `/opt/ocr`, con los pesos en `/opt/ocr/models`, durante la construcción. El modelo PP-OCRv5 se descarga del repositorio oficial RapidAI y se verifica mediante SHA-256; PP-OCRv4 se incluye en el paquete fijado RapidOCR. No hay descargas de modelos al recibir solicitudes. En Linux sin Docker se necesita un entorno equivalente y configurar sus rutas privadas.

Hay un bloqueo local para ejecutar solo un proceso PP-OCR a la vez por instancia. Si está ocupado, falla o se agota el tiempo global de 110 segundos, el trámite conserva revisión manual. Cada ejecución recibe una imagen de hasta 25 millones de píxeles y emplea dos hilos CPU. Debe comprobarse el consumo de memoria y tiempo en el alojamiento concreto; las puntuaciones del motor no son porcentajes garantizados de exactitud. Los documentos manuscritos pueden requerir revisión humana.

Python PSF, RapidOCR/PP-OCR Apache-2.0, ONNX Runtime MIT, NumPy BSD, Pillow MIT-CMU y OpenCV Apache-2.0. Conservar los avisos incluidos en sus paquetes. Referencia de modelos y atribuciones: https://github.com/RapidAI/RapidOCR/blob/main/python/MODEL_LICENSES.md.
