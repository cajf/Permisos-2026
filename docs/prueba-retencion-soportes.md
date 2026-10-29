# Comprobar soportes tras una validación

Utilice una cuenta y una base de pruebas, con JavaScript habilitado.

1. Abra Nueva solicitud y elija un tipo con soporte obligatorio.
2. Diligencie un motivo y una fecha hábil libre. Elija una franja dentro del almuerzo para provocar la validación de tiempo laborable.
3. Seleccione un PDF y una imagen PNG válidos. Envíe la solicitud.
4. Compruebe que se muestra el error sin navegar fuera del formulario. Deben conservarse el tipo, el motivo, las fechas y los dos archivos seleccionados. No debe existir un radicado nuevo por el intento inválido.
5. Corrija únicamente las horas y vuelva a enviar, sin abrir el selector de archivos.
6. Compruebe que se crea un único radicado con ambos soportes. Descárguelos y compare sus contenidos con los originales.
7. Repita en Corregir y reenviar para una solicitud devuelta. Los soportes ya guardados y los nuevos seleccionados deben conservarse ante otra validación.

El envío mantiene el formulario en pantalla y muestra el mensaje del servidor. Las validaciones, autorizaciones y la protección CSRF siguen ejecutándose en el servidor. La conservación de la selección corresponde al formulario abierto en el navegador; no es un borrador persistente para recuperarlo después de cerrar o recargar la página. No se crean copias temporales adicionales en la base ni se alteran los soportes existentes.
