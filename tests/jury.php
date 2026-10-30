<?php
$juryText="REGISTRADURIA NACIONAL DEL ESTADO CIVIL\nCONSTANCIA DE PRESTACIÓN DE SERVICIOS\nJURADO DE VOTACION\nELECCIONES 25 DE OCTUBRE DE 2026\nCEDULA No. 1234567890";
$lines=array_map(fn($text)=>['text'=>$text,'confidence'=>99],explode("\n",$juryText));
$parsed=ElectoralCertificate::parse($lines,'jurado');
check($parsed['recognized'] && $parsed['documents']===['1234567890'],'Reconoce constancia de prestación como jurado');
check(!ElectoralCertificate::parse($lines)['recognized'],'Constancia de jurado no se confunde con certificado de sufragio');
$report=['kind'=>'jurado','documents'=>[$parsed+['sha256'=>str_repeat('a',64)]],'reasons'=>[]];
$limit=ElectoralCertificate::deadline('2026-10-25','jurado');$calendar=new WorkCalendar();$count=0;
for($at=new DateTimeImmutable('2026-10-26');$at->format('Y-m-d')<=$limit;$at=$at->modify('+1 day'))if($calendar->isBusinessDay($at->format('Y-m-d'))) $count++;
check($count===45 && $calendar->isBusinessDay($limit),'Plazo cuenta exactamente 45 días hábiles excluyendo festivos');
check(ElectoralCertificate::validate($report,'1234567890',[['date'=>$limit]],'2026-10-29')['status']==='verified','Admite día hábil 45');
refuses(fn()=>ElectoralCertificate::validate($report,'1234567890',[['date'=>(new DateTimeImmutable($limit))->modify('+1 day')->format('Y-m-d')]],'2026-10-29'),'Bloquea fecha posterior al día hábil 45');
refuses(fn()=>ElectoralCertificate::validate($report,'99999999',[['date'=>'2026-11-20']],'2026-10-29'),'Bloquea cédula distinta para jurado');
function jurySubmit(string $role,array $slots):int {
 global $wf;$c=cat('jurado');$uid=1;
 $stub=insert('requests',['user_id'=>$uid,'category_id'=>$c['id'],'status'=>'returned','justification'=>'Prueba de jurado','total_minutes'=>1,'snapshot'=>'{}','revision'=>0,'created_at'=>now(),'updated_at'=>now()]);
 insert('attachments',['user_id'=>$uid,'request_id'=>$stub,'original_name'=>'muestra.pdf','stored_name'=>bin2hex(random_bytes(24)),'mime'=>'application/pdf','size'=>1,'sha256'=>str_repeat('a',64),'created_at'=>now()]);
 return $wf->submit(person($uid),['category_id'=>$c['id'],'jury_role'=>$role,'slots'=>$slots,'justification'=>'Prueba de clasificación jurado y clavero','exception_reason'=>'Prueba funcional aislada'],[],$stub,new DateTimeImmutable('2026-10-29'));
}
refuses(fn()=>jurySubmit('',[['date'=>'2026-11-20','mode'=>'full']]),'Exige selección de modalidad');
refuses(fn()=>jurySubmit('clavero',[slot('2026-11-20','07:00','08:00')]),'Clavero no se solicita por horas');
refuses(fn()=>jurySubmit('jurado',[['date'=>'2026-11-20','mode'=>'full'],['date'=>'2026-11-23','mode'=>'full']]),'Jurado no permite dos jornadas');
$id=jurySubmit('clavero',[['date'=>'2026-11-20','mode'=>'full']]);
check((json_decode(row('SELECT snapshot FROM requests WHERE id=?',[$id])['snapshot'],true)['jury_role']??'')==='clavero','Conserva modalidad clavero');
$wf->review(person(2),$id,'approved','');$wf->review(person(3),$id,'approved','');
check(row('SELECT status FROM requests WHERE id=?',[$id])['status']==='approved','Clavero no exige revisión OCR de jurado');
$id=jurySubmit('jurado',[['date'=>'2026-11-23','mode'=>'full']]);
check(ElectoralCertificate::needsManual(row('SELECT * FROM requests WHERE id=?',[$id])),'Soporte ilegible registra jurado con revisión manual');
refuses(fn()=>$wf->review(person(2),$id,'approved',''),'Jurado manual exige constancia del aprobador');
$wf->review(person(2),$id,'approved','','','Verifiqué cédula, servicio como jurado y plazo de 45 días hábiles.');
check(row('SELECT status FROM requests WHERE id=?',[$id])['status']==='pending','Revisión manual del jefe conserva segunda etapa');

$juryImage=ROOT.'/.work/jury-sample.png';$im=imagecreatetruecolor(2200,900);imagefill($im,0,0,imagecolorallocate($im,255,255,255));$ink=imagecolorallocate($im,0,0,0);
foreach(explode("\n",$juryText) as $i=>$line) imagettftext($im,30,0,50,100+$i*110,$ink,'C:/Windows/Fonts/arial.ttf',$line);
imagepng($im,$juryImage);imagedestroy($im);
$read=ElectoralCertificate::readFiles([['tmp'=>$juryImage,'mime'=>'image/png','original_name'=>'JURADO-MUESTRA-SIN-VALIDEZ.png','sha256'=>hash_file('sha256',$juryImage)]],'jurado');
check(ElectoralCertificate::validate($read,'1234567890',[['date'=>'2026-11-24']],'2026-10-29')['status']==='verified','Tesseract real reconoce constancia de servicio como jurado');
