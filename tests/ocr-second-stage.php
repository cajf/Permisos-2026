<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
// Reutiliza evidencia de una lectura real para probar validaciones sin registrar trámites.
ensure($argc===4,'Uso: php tests/ocr-second-stage.php informe.json cedula fecha-elecciones');
$report=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);$id=$argv[2];$date=$argv[3];$checks=0;
function stageCheck(bool $ok,string $label):void {ensure($ok,$label);echo 'OK '.$label.PHP_EOL;$GLOBALS['checks']++;}
$document=$report['documents'][0];
stageCheck(($document['second_stage']??'')==='PP-OCRv4-detection/PP-OCRv5-recognition','Se ejecutó la segunda etapa real desde PHP');
stageCheck($document['recognized']===true && $document['documents']===[$id] && $document['dates']===[$date],'Se identificaron los tres campos esperados');
$slots=[['date'=>(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d')]];
stageCheck(ElectoralCertificate::validate($report,$id,$slots,$date)['status']==='verified','La lectura completa supera validación con identidad y periodo coincidentes');
foreach([['999999999999',$slots],[$id,[['date'=>(new DateTimeImmutable($date))->modify('+31 days')->format('Y-m-d')]]]] as [$person,$days]) {
    $blocked=false;try {ElectoralCertificate::validate($report,$person,$days,$date);}catch(DomainException){$blocked=true;}
    stageCheck($blocked,'Se conserva el bloqueo por identidad ajena o plazo excedido');
}
$manual=$report;$manual['documents'][0]['documents']=[];$manual['reasons'][]='Motores discordantes';
stageCheck(ElectoralCertificate::validate($manual,$id,$slots,$date)['status']==='manual','Una discrepancia no se convierte en validación automática');
echo "$checks comprobaciones correctas; no se modificaron bases de datos.\n";
