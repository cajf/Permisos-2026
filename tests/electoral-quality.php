<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$checks=0;
function qualityCheck(bool $ok,string $message):void {
    if(!$ok) throw new RuntimeException($message);
    echo 'OK '.$message.PHP_EOL;$GLOBALS['checks']++;
}
function qualityTsv(array $field):string {
    $groups=[['REGISTRADURIA','NACIONAL'],['CERTIFICADO','ELECTORAL'],['ELECCIONES','25','DE','OCTUBRE','DE','2026'],$field];
    $tsv="level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n";
    foreach($groups as $line=>$words) foreach($words as $i=>$word) {
        [$word,$confidence]=is_array($word)?$word:[$word,99];
        $tsv.="5\t1\t1\t1\t".($line+1)."\t".($i+1)."\t0\t0\t10\t10\t$confidence\t$word\n";
    }
    return $tsv;
}
$result=ElectoralCertificate::fromTsv(qualityTsv(['Cedula','1234567890','Nombres',['mancha',12]]));
qualityCheck($result['documents']===['1234567890'],'Una palabra dudosa en nombres no invalida la cédula legible');
qualityCheck($result['dates']===['2026-10-25'],'La lectura por campos conserva la fecha electoral');
foreach([
    ['Cedula','1234',['x',10],'5678'],
    ['Cedula',['1234567890',50]],
    ['Cedula','1234',['5678',50]],
] as $tokens) qualityCheck(ElectoralCertificate::fromTsv(qualityTsv($tokens))['documents']===[],'No acepta cédulas dudosas o parcialmente legibles');
qualityCheck(ElectoralCertificate::fromTsv(qualityTsv(['Mesa','1234567890']))['documents']===[],'El número de mesa no se interpreta como cédula');
$report=['kind'=>'sufragar','documents'=>[$result+['sha256'=>str_repeat('a',64)]],'reasons'=>[]];
qualityCheck(ElectoralCertificate::validate($report,'1234567890',[['date'=>'2026-11-02']],'2026-11-03')['status']==='verified','Una lectura inequívoca pasa la validación de identidad y plazo');
foreach([['999999999','2026-11-02'],['1234567890','2026-11-25']] as [$id,$date]) {
    $blocked=false;
    try {ElectoralCertificate::validate($report,$id,[['date'=>$date]],'2026-11-03');} catch(DomainException) {$blocked=true;}
    qualityCheck($blocked,'La mejora conserva el bloqueo de cédula ajena o disfrute fuera de plazo');
}
$root=ROOT.'/.work/ocr-quality-'.bin2hex(random_bytes(4));mkdir($root);
try {
    $text=['REGISTRADURIA NACIONAL DEL ESTADO CIVIL','CERTIFICADO ELECTORAL','ELECCIONES 25 DE OCTUBRE DE 2026','Cedula 1234567890','MUESTRA FICTICIA SIN VALIDEZ'];
    $image=imagecreatetruecolor(1800,700);imagefill($image,0,0,imagecolorallocate($image,255,255,255));
    foreach($text as $i=>$line) imagettftext($image,30,0,50,90+$i*110,imagecolorallocate($image,0,0,0),'C:/Windows/Fonts/arial.ttf',$line);
    $angleMethod=new ReflectionMethod(ElectoralCertificate::class,'skewAngle');
    qualityCheck($angleMethod->invoke(null,$image)===0,'No gira una imagen que ya está alineada');
    $enhance=new ReflectionMethod(ElectoralCertificate::class,'enhancedImage');
    foreach([0,3,-3] as $angle) {
        $rotated=imagerotate($image,$angle,imagecolorallocate($image,255,255,255));
        if($angle!==0) qualityCheck(abs($angleMethod->invoke(null,$rotated)+$angle)<=1,'Detecta el sentido de corrección de la inclinación '.$angle);
        $path=$root.'/source.png';imagepng($rotated,$path);imagedestroy($rotated);
        $prepared=$root.'/prepared.png';
        qualityCheck($enhance->invoke(null,$path,$prepared),'Prepara imagen con inclinación '.$angle);
        $report=ElectoralCertificate::readFiles([['tmp'=>$prepared,'mime'=>'image/png','original_name'=>'MUESTRA-FICTICIA.png','sha256'=>hash_file('sha256',$prepared)]]);
        $document=$report['documents'][0]??[];
        qualityCheck(($document['documents']??[])===['1234567890'] && ($document['dates']??[])===['2026-10-25'],'OCR real recupera cédula y fecha de la muestra con inclinación '.$angle);
    }
    $pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->AddPage();$pdf->Image($prepared,10,20,190);
    $pdfPath=$root.'/sample.pdf';$pdf->Output($pdfPath,'F');
    $report=ElectoralCertificate::readFiles([['tmp'=>$pdfPath,'mime'=>'application/pdf','original_name'=>'MUESTRA-FICTICIA.pdf','sha256'=>hash_file('sha256',$pdfPath)]]);
    qualityCheck(($report['documents'][0]['documents']??[])===['1234567890'] && ($report['documents'][0]['dates']??[])===['2026-10-25'],'PDF real convertido a mayor resolución conserva cédula y fecha');
    imagedestroy($image);
} finally {foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
echo "$checks comprobaciones correctas, sin acceso a bases de datos.\n";
