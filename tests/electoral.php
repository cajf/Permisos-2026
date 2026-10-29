<?php
declare(strict_types=1);
// Se incluye desde core.php: usa exclusivamente la base aislada de la suite.
function electoralLines(string $text,float $confidence=99):array {
    return array_map(fn($line)=>['text'=>$line,'confidence'=>$confidence],explode("\n",$text));
}
$text="REGISTRADURÍA NACIONAL DEL ESTADO CIVIL\nCERTIFICADO ELECTORAL\nELECCIONES 25 DE OCTUBRE DE 2026\nCédula No. 1.234.567.890\nNombres y Apellidos PERSONA FICTICIA\nMUESTRA SIN VALIDEZ";
$parsed=ElectoralCertificate::parse(electoralLines($text));
check($parsed['recognized'] && $parsed['documents']===['1234567890'] && $parsed['dates']===['2026-10-25'],'OCR interpreta certificado colombiano, cédula y fecha contextualizada');
$report=['version'=>1,'status'=>'manual','checked_at'=>now(),'documents'=>[$parsed+['sha256'=>str_repeat('a',64),'file'=>'muestra.png']],'reasons'=>[]];
$dates=fn($date)=>[['date'=>$date]];
check(ElectoralCertificate::validate($report,'1234567890',$dates('2026-10-26'),'2026-10-29')['status']==='verified','Sufragar incluye el primer día posterior a las elecciones');
check(ElectoralCertificate::validate($report,'1.234.567.890',$dates('2026-11-24'),'2026-10-29')['status']==='verified','Sufragar incluye el día treinta y normaliza separadores de cédula');
refuses(fn()=>ElectoralCertificate::validate($report,'999999999',$dates('2026-11-02'),'2026-10-29'),'Sufragar bloquea cédula ajena legible');
refuses(fn()=>ElectoralCertificate::validate($report,'1234567890',$dates('2026-10-25'),'2026-10-29'),'Sufragar excluye el día de elecciones');
refuses(fn()=>ElectoralCertificate::validate($report,'1234567890',$dates('2026-11-25'),'2026-10-29'),'Sufragar bloquea el día treinta y uno');
refuses(fn()=>ElectoralCertificate::validate($report,'1234567890',[['date'=>'2026-11-02'],['date'=>'2026-11-25']],'2026-10-29'),'Sufragar verifica todas las fechas solicitadas');
check(!ElectoralCertificate::parse(electoralLines('CERTIFICADO LABORAL CEDULA 1234567890'))['recognized'],'Un documento distinto no se confunde con certificado electoral');
check(!ElectoralCertificate::parse(electoralLines($text,60))['recognized'],'OCR de baja confianza no bloquea con datos adivinados');
$issueOnly=str_replace('ELECCIONES 25 DE OCTUBRE DE 2026','FECHA DE EXPEDICION 25 DE OCTUBRE DE 2026',$text);
check(ElectoralCertificate::parse(electoralLines($issueOnly))['dates']===[],'Fecha de expedición no se toma como fecha electoral');
$numeric=str_replace('25 DE OCTUBRE DE 2026','25/10/2026',$text);
check(ElectoralCertificate::parse(electoralLines($numeric))['dates']===['2026-10-25'],'OCR admite fecha numérica colombiana');
$unreadable=['version'=>1,'status'=>'manual','documents'=>[],'reasons'=>['Soporte ilegible']];
check(ElectoralCertificate::validate($unreadable,'1234567890',$dates('2026-11-25'))['status']==='manual','Falta de lectura conserva trámite para revisión manual');
// OCR real en una imagen y un PDF ficticios; nunca usa documentos personales.
$sample=ROOT.'/.work/electoral-sample.png';$image=imagecreatetruecolor(2000,1000);imagefill($image,0,0,imagecolorallocate($image,255,255,255));$ink=imagecolorallocate($image,0,0,0);
foreach(explode("\n",$text) as $i=>$line) imagettftext($image,30,0,60,100+$i*110,$ink,'C:/Windows/Fonts/arial.ttf',$line);
imagepng($image,$sample);imagedestroy($image);
$file=['tmp'=>$sample,'mime'=>'image/png','original_name'=>'MUESTRA-SIN-VALIDEZ.png','size'=>filesize($sample),'sha256'=>hash_file('sha256',$sample)];
$real=ElectoralCertificate::readFiles([$file]);
$verified=ElectoralCertificate::validate($real,'1234567890',$dates('2026-11-02'),'2026-10-29');
check($verified['status']==='verified','Motor Tesseract real lee imagen de muestra en español');
$pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->AddPage();$pdf->Image($sample,10,20,190);$pdfPath=ROOT.'/.work/electoral-sample.pdf';$pdf->Output($pdfPath,'F');
$realPdf=ElectoralCertificate::readFiles([['tmp'=>$pdfPath,'mime'=>'application/pdf','original_name'=>'MUESTRA.pdf','sha256'=>hash_file('sha256',$pdfPath)]]);
check(ElectoralCertificate::validate($realPdf,'1234567890',$dates('2026-11-02'),'2026-10-29')['status']==='verified','OCR real rasteriza y lee un PDF escaneado sin capa de texto');
$originalOcr=$GLOBALS['config']['electoral_ocr'];$GLOBALS['config']['electoral_ocr']['tesseract']=ROOT.'/.work/motor-inexistente.exe';
$fallback=ElectoralCertificate::readFiles([$file]);
check($fallback['status']==='manual' && count($fallback['reasons'])>0,'Ausencia del motor genera revisión manual sin excepción de registro');
$GLOBALS['config']['electoral_ocr']=$originalOcr;
$uid=insert('users',['name'=>'Solicitante electoral ficticio','identification'=>'1234567890','username'=>'electoral.qa','email'=>'electoral@ejemplo.invalid','password'=>password_hash('PruebaLocal!9876',PASSWORD_DEFAULT),'job_title'=>'Profesional','department_id'=>1,'manager_id'=>2,'roles'=>'[]','force_password'=>0]);
$input=['category_id'=>cat('sufragar')['id'],'slots'=>[slot('2026-11-17','07:30','08:30')],'justification'=>'Prueba aislada de certificado electoral','exception_reason'=>'Excepción de prueba funcional'];
$GLOBALS['config']['electoral_ocr']['tesseract']=ROOT.'/.work/motor-inexistente.exe';
// Sin archivos temporales inventados: en modo mysql se conserva el original de muestra.
$oldStorage=$GLOBALS['config']['attachment_storage'];$GLOBALS['config']['attachment_storage']='mysql';
$id=$wf->submit(person($uid),$input,[$file],null,new DateTimeImmutable('2026-10-29 06:00:00'));
$GLOBALS['config']['attachment_storage']=$oldStorage;$GLOBALS['config']['electoral_ocr']=$originalOcr;
check(row('SELECT status FROM requests WHERE id=?',[$id])['status']==='pending' && ElectoralCertificate::needsManual(row('SELECT * FROM requests WHERE id=?',[$id])),'Fallo OCR registra la solicitud y conserva evidencia manual en MySQL');
refuses(fn()=>$wf->review(person(2),$id,'approved',''),'Aprobación individual exige constancia manual por OCR no concluyente');
$other=testSubmit($uid,'academico',[slot('2026-12-30','07:30','08:30')]);
$batch=$wf->approveMany(person(2),[approvalId($id),approvalId($other)]);
check(count($batch['failed'])===1 && count($batch['approved'])===1 && $batch['failed'][0]['request_id']===$id,'Lote informa falta de revisión electoral y continúa con las demás solicitudes');
$managerReason='Verifiqué cédula 1234567890 y elecciones 25/10/2026; disfrute 17/11/2026 dentro del plazo.';
$wf->review(person(2),$id,'approved','', '',$managerReason);
check(str_contains(row('SELECT comment FROM approvals WHERE request_id=? AND step=1',[$id])['comment'],'Revisión manual del certificado electoral'),'Primera etapa registra responsable y constancia electoral');
refuses(fn()=>$wf->review(person(3),$id,'approved',''),'Subsecretario también verifica manualmente el certificado');
$batch=$wf->approveMany(person(3),[approvalId($id,2)],'',[],[$id=>$managerReason]);
check(count($batch['approved'])===1 && row('SELECT status FROM requests WHERE id=?',[$id])['status']==='approved','Aprobación masiva final admite constancia manual individual y guarda la decisión');
$r=row('SELECT * FROM requests WHERE id=?',[$id]);check(str_contains(requestPdf($r),'%PDF-'),'Constancia PDF incluye revisión electoral y soporte recuperado');
$GLOBALS['config']['attachment_storage']='mysql';
$validInput=$input;$validInput['slots']=[slot('2026-11-18','07:30','08:30')];
$validId=$wf->submit(person($uid),$validInput,[$file],null,new DateTimeImmutable('2026-10-29 06:00:00'));
check(!ElectoralCertificate::needsManual(row('SELECT * FROM requests WHERE id=?',[$validId])),'Registro real con certificado legible conserva resultado verificado');
$wf->review(person(2),$validId,'approved','');$wf->review(person(3),$validId,'approved','');
check(row('SELECT status FROM requests WHERE id=?',[$validId])['status']==='approved','Ambas etapas aprueban OCR verificado sin constancia manual adicional');
$count=(int)row('SELECT COUNT(*) n FROM requests')['n'];
$badInput=$input;$badInput['slots']=[slot('2026-11-25','07:30','08:30')];
try {$wf->submit(person($uid),$badInput,[$file],null,new DateTimeImmutable('2026-10-29 06:00:00'));throw new RuntimeException('Se admitió disfrute fuera de plazo');}
catch(DomainException $e){check(str_contains($e->getMessage(),'30 días calendario'),'Registro real bloquea el plazo por la validación electoral');}
check((int)row('SELECT COUNT(*) n FROM requests')['n']===$count,'Inconsistencia OCR no deja solicitud parcial ni guarda adjuntos');
$GLOBALS['config']['attachment_storage']=$oldStorage;
