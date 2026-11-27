<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$checks=0;
function detailCheck(bool $ok,string $message):void {ensure($ok,$message);echo 'OK '.$message.PHP_EOL;$GLOBALS['checks']++;}
$base=['recognized'=>true,'documents'=>['1234567890'],'dates'=>['2026-10-25']];
$summary=ElectoralCertificate::summary(['status'=>'manual','documents'=>[array_replace($base,['documents'=>[]])]]);
detailCheck(str_contains($summary,'No fue posible leer con certeza: cédula.')&&!str_contains($summary,'leer con certeza: tipo'),'Identifica únicamente la cédula faltante');
$summary=ElectoralCertificate::summary(['status'=>'manual','documents'=>[array_replace($base,['dates'=>[]])]]);
detailCheck(str_contains($summary,'No fue posible leer con certeza: fecha de elecciones.'),'Identifica la fecha faltante');
$text=[['text'=>'CERTIFICADO ELECTORAL','confidence'=>99],['text'=>'CEDULA 1234567890','confidence'=>99],['text'=>'ELECCIONES 25 DE OCTUBRE DE 2026','confidence'=>99]];
$parsed=ElectoralCertificate::parse($text);
detailCheck(!$parsed['recognized']&&$parsed['documents']===[]&&$parsed['dates']===[],'El estado por campo no cambia la aceptación de un certificado sin institución reconocida');
$summary=ElectoralCertificate::summary(['documents'=>[$parsed]]);
detailCheck(str_contains($summary,'No fue posible leer con certeza: tipo de certificado.')&&!str_contains($summary,'certeza: cédula'),'Distingue tipo no confirmado de cédula y fecha legibles');
$status=ElectoralCertificate::combinedFieldStatus($base,array_replace($base,['documents'=>['9876543210']]));
detailCheck($status['document_number']==='ambiguous'&&$status['election_date']==='read','Conserva la discrepancia de cédula sin marcar erróneamente la fecha');
$summary=ElectoralCertificate::summary(['documents'=>[array_replace($base,['documents'=>[],'dates'=>[],'field_status'=>$status])]]);
detailCheck(str_contains($summary,'Lectura ambigua o contradictoria: cédula.')&&!str_contains($summary,'certeza: fecha'),'Explica el conflicto aunque se hayan vaciado los candidatos para impedir su validación');
$summary=ElectoralCertificate::summary(['kind'=>'jurado','documents'=>[]]);
detailCheck(str_contains($summary,'CONSTANCIA DE JURADO')&&str_contains($summary,'tipo de certificado, la cédula ni la fecha'),'Los informes vacíos conservan aviso completo para jurado');
$summary=ElectoralCertificate::summary(['documents'=>[$base,array_replace($base,['dates'=>['2026-10-26']])]]);
detailCheck(str_contains($summary,'valores distintos entre las lecturas'),'Detecta fechas diferentes entre soportes');
$summary=ElectoralCertificate::summary(['status'=>'verified','document_number'=>'1234567890','election_date'=>'2026-10-25','valid_until'=>'2026-11-24']);
detailCheck(str_contains($summary,'Lectura OCR completada')&&!str_contains($summary,'REVISIÓN MANUAL'),'Conserva el mensaje de validación satisfactoria');
foreach(['new','request','layout'] as $view) detailCheck(!str_contains(file_get_contents(ROOT.'/views/'.$view.'.php'),'electoral-preview.php'),'Retira el mensaje temporal de '.$view);
echo "$checks comprobaciones correctas, sin acceso a bases de datos.\n";
