<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$checks=0;
function previewCheck(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException($message);
    echo 'OK '.$message.PHP_EOL;$GLOBALS['checks']++;
}
function previewHtml(array $electoralReport):string {
    ob_start();require ROOT.'/views/electoral-preview.php';return ob_get_clean();
}
$document=['recognized'=>true,'documents'=>['1234567890'],'dates'=>['2026-10-25'],'file'=>'MUESTRA.pdf','read_fields'=>['certificate_type'=>['CERTIFICADO ELECTORAL']]];
$html=previewHtml(['kind'=>'sufragar','status'=>'manual','documents'=>[$document]]);
previewCheck(str_contains($html,'1234567890')&&str_contains($html,'2026-10-25'),'Muestra los datos leídos incluso con revisión manual pendiente');
previewCheck(str_contains($html,'CERTIFICADO ELECTORAL')&&str_contains($html,'Mensaje temporal'),'Muestra el encabezado leído y el carácter temporal');
$juryDocument=array_replace($document,['read_fields'=>['certificate_type'=>['CONSTANCIA DE SERVICIO COMO JURADO']]]);
$html=previewHtml(['kind'=>'jurado','documents'=>[$juryDocument]]);
previewCheck(str_contains($html,'CONSTANCIA DE SERVICIO COMO JURADO'),'Muestra el texto leído de la constancia de jurado');
$html=previewHtml(['documents'=>[['recognized'=>true,'documents'=>['1234567890'],'dates'=>[]]]]);
previewCheck(str_contains($html,'1234567890')&&str_contains($html,'Sin texto capturado para este campo'),'Muestra una lectura parcial sin inventar la fecha');
$html=previewHtml(['documents'=>[]]);
previewCheck(str_contains($html,'No se pudieron identificar datos'),'Informa si no existen datos de lectura');
$html=previewHtml(['documents'=>[array_replace($document,['documents'=>['1234','5678'],'dates'=>['2026-10-25','2026-10-26']])]]);
previewCheck(str_contains($html,'Lectura ambigua')&&str_contains($html,'1234 / 5678'),'Expone candidatos múltiples sin presentarlos como una validación');
$html=previewHtml(['documents'=>[array_replace($document,['file'=>'<script>alert(1)</script>.pdf'])]]);
previewCheck(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'El nombre del adjunto se escapa antes de mostrarlo');
$observed=ElectoralCertificate::parse([
    ['text'=>'CONSTANCIA DE JURADO DE VOTACION','confidence'=>35],
    ['text'=>'Cédula 1O98O23','confidence'=>30],
    ['text'=>'ELECCIONES 32/13/2026','confidence'=>20],
],'jurado');
$html=previewHtml(['kind'=>'jurado','documents'=>[$observed]]);
previewCheck(str_contains($html,'CONSTANCIA DE JURADO DE VOTACION')&&str_contains($html,'1O98O23')&&str_contains($html,'32/13/2026'),'Muestra el texto efectivamente leído, incluso erróneo y con baja confianza');
previewCheck(!$observed['recognized']&&$observed['documents']===[]&&$observed['dates']===[],'El texto diagnóstico no se utiliza para validar el certificado');
$html=previewHtml(['documents'=>[['read_fields'=>['certificate_type'=>['<script>dato</script>']]]]]);
previewCheck(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Escapa también el texto obtenido mediante OCR');
echo "$checks comprobaciones correctas, sin acceso a bases de datos.\n";
