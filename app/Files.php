<?php
declare(strict_types=1);

function validateUploads(array $files): array {
    if (!isset($files['name'])) return [];
    $out=[];
    $names=is_array($files['name'])?$files['name']:[$files['name']];
    ensure(count($names)<=10,'Se permiten hasta diez soportes por carga.');
    foreach ($names as $i=>$name) {
        $get=fn($k)=>is_array($files[$k])?$files[$k][$i]:$files[$k];
        if ($get('error')===UPLOAD_ERR_NO_FILE) continue;
        ensure($get('error')===UPLOAD_ERR_OK,'No se pudo recibir uno de los archivos. Revise su tamaño.');
        $path=$get('tmp_name');
        ensure(is_uploaded_file($path),'El archivo no proviene de una carga válida.');
        ensure($get('size')>0 && $get('size')<=12*1024*1024,'Cada soporte debe pesar entre 1 byte y 12 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        ensure(in_array($mime,['application/pdf','image/jpeg','image/png'],true),'Solo se admiten PDF, JPG y PNG.');
        if ($mime==='application/pdf') {
            try {
                $reader=new \setasign\Fpdi\Tcpdf\Fpdi();
                $count=$reader->setSourceFile($path);
                ensure($count<=100,'Cada PDF admite hasta 100 páginas.');
                for ($page=1;$page<=$count;$page++) $reader->importPage($page);
            } catch (DomainException $e) { throw $e; }
            catch (Throwable $e) { throw new DomainException('El PDF no se puede incorporar: guárdelo sin contraseña como PDF compatible y vuelva a cargarlo.'); }
        } else {
            $size=getimagesize($path);
            ensure($size!==false && $size[0]*$size[1]<=25000000,'La imagen no es válida o supera 25 megapíxeles.');
        }
        $out[]=['tmp'=>$path,'original_name'=>mb_substr(basename(str_replace('\\','/',$name)),0,180),'mime'=>$mime,'size'=>(int)$get('size'),'sha256'=>hash_file('sha256',$path)];
    }
    return $out;
}
function saveUploads(array $files,int $owner,?int $request=null,?int $act=null): void {
    foreach ($files as $file) {
        $stored=bin2hex(random_bytes(24));
        $source=$file['tmp'];
        if ($GLOBALS['config']['attachment_storage']!=='mysql') ensure(move_uploaded_file($source,uploadDir().'/'.$stored),'No se pudo almacenar el soporte.');
        unset($file['tmp']);
        $id=insert('attachments',$file+['user_id'=>$owner,'request_id'=>$request,'act_id'=>$act,'stored_name'=>$stored,'created_at'=>now()]);
        if ($GLOBALS['config']['attachment_storage']==='mysql') {
            $bytes=file_get_contents($source);
            ensure($bytes!==false && hash('sha256',$bytes)===$file['sha256'],'No se pudo comprobar el soporte antes de guardarlo.');
            insert('attachment_contents',['attachment_id'=>$id,'content_base64'=>base64_encode($bytes)]);
        }
    }
}
/** La copia local es una caché; el original persistente de la nube está en MySQL. */
function attachmentPath(array $attachment,?PDO $pdo=null,?string $directory=null): string {
    $pdo??=db();$directory??=uploadDir();$name=$attachment['stored_name'];
    ensure(preg_match('/^[a-f0-9]{48}$/D',$name)===1,'Nombre interno de soporte inválido.');
    $path=$directory.'/'.$name;
    if (is_file($path) && hash_file('sha256',$path)===$attachment['sha256']) return $path;
    ensure(tableExists('attachment_contents',$pdo),'No se encuentra el soporte original.');
    $query=$pdo->prepare('SELECT content_base64 FROM attachment_contents WHERE attachment_id=?');$query->execute([$attachment['id']]);
    $encoded=$query->fetchColumn();$bytes=$encoded===false?false:base64_decode($encoded,true);
    ensure($bytes!==false && strlen($bytes)===(int)$attachment['size'] && hash('sha256',$bytes)===$attachment['sha256'],'No se pudo recuperar un soporte íntegro de MySQL.');
    if (!is_dir($directory)) ensure(mkdir($directory,0700,true),'No se pudo crear la caché de soportes.');
    $temporary=tempnam($directory,'.attachment-');
    ensure($temporary!==false,'No se pudo crear la copia temporal del soporte.');
    try {
        ensure(file_put_contents($temporary,$bytes,LOCK_EX)===strlen($bytes),'No se pudo escribir el soporte recuperado.');
        chmod($temporary,0600);
        ensure(rename($temporary,$path),'No se pudo recuperar el archivo de soporte.');
    } finally {if (is_file($temporary)) unlink($temporary);}
    return $path;
}
function canReadRequest(array $u,array $r): bool {
    return (int)$r['user_id']===(int)$u['id'] || hasRole($u,'admin') || (int)($r['cancellation_assignee_id']??0)===(int)$u['id'] || row('SELECT id FROM approvals WHERE request_id=? AND (assigned_user_id=? OR acted_by=?)',[$r['id'],$u['id'],$u['id']])!==null;
}
function requestPdf(array $r): string {
    $c=row('SELECT * FROM categories WHERE id=?',[$r['category_id']]);
    $snap=json_decode($r['snapshot'],true,512,JSON_THROW_ON_ERROR);
    $pdf=new \setasign\Fpdi\Tcpdf\Fpdi();
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(true); $pdf->SetCreator('Gestión del Talento Humano');
    $pdf->SetTitle('Solicitud '.sprintf('TH-%06d',$r['id'])); $pdf->SetMargins(18,18,18); $pdf->SetAutoPageBreak(true,18); $pdf->SetFont('dejavusans','',10);
    $pdf->AddPage();
    $html='<h1>Alcaldía de Bucaramanga</h1><h2>Solicitud '.sprintf('TH-%06d',$r['id']).'</h2><p>Gestión del Talento Humano · F-GAT-8100-238,37-183</p>';
    $data=['Estado'=>statusLabel($r['status']),'Tipo'=>familyLabel($c['family']).' · '.$c['name'],'Funcionario'=>$snap['name'],'Cédula'=>$snap['identification'],'Cargo'=>$snap['job_title'],'Código / grado'=>$snap['code'].' / '.$snap['grade'],'Dependencia'=>$snap['department'],'Fecha de registro'=>$r['created_at'].' (America/Bogota)','Duración'=>hours((int)$r['total_minutes']),'Justificación'=>$r['justification'],'Excepción de anticipación'=>$r['exception_reason'],'Parentesco'=>$r['relationship']];
    $html.='<table cellpadding="6" border="1">'; foreach ($data as $k=>$v) $html.='<tr><td width="32%"><b>'.e($k).'</b></td><td width="68%">'.nl2br(e($v)).'</td></tr>'; $html.='</table><h3>Fechas y horarios</h3>';
    if(($c['slug']==='sufragar' || ($c['slug']==='jurado' && ($snap['jury_role']??'')!=='clavero'))) $html.='<h3>Validación del certificado electoral</h3><p>'.e(ElectoralCertificate::summary(ElectoralCertificate::report($r)??['status'=>'manual'])).'</p><p>El OCR no comprueba autenticidad ante la Registraduría.</p>';
    if ($r['birthday_year']) $html.='<p>Beneficio de cumpleaños de la vigencia '.(int)$r['birthday_year'].'.</p>';
    foreach (rows('SELECT * FROM request_slots WHERE request_id=? ORDER BY date,start_time',[$r['id']]) as $s) {
        $html.='<p>'.e(($s['start_at']??($s['date'].' '.$s['start_time'])).' a '.($s['end_at']??($s['date'].' '.$s['end_time'])).' · '.hours((int)$s['minutes'])).'</p>';
    }
    if($c['slug']==='jurado') $html.='<p>Modalidad: '.e($snap['jury_role']??'Sin clasificar').'</p>';
    $html.='<h3>Constancias e historial</h3><p>Las actuaciones se registran con fecha y hora de Bogotá. Este documento no contiene firma digital.</p>';
    foreach (rows('SELECT e.*,u.name FROM events e JOIN users u ON u.id=e.actor_id WHERE request_id=? ORDER BY e.id',[$r['id']]) as $ev) $html.='<p><b>'.e($ev['created_at'].' · '.$ev['name']).'</b><br>'.e($ev['action'].' · '.$ev['detail']).'</p>';
    $acts=rows('SELECT a.number,l.minutes,l.returned_minutes,l.state FROM allocations l JOIN acts a ON a.id=l.act_id WHERE request_id=?',[$r['id']]);
    if ($acts) { $html.='<h3>Actos administrativos</h3>'; foreach ($acts as $a) $html.='<p>'.e($a['number'].' · '.hours((int)$a['minutes']).' · '.statusLabel($a['state']).' · Reintegrado: '.hours((int)$a['returned_minutes'])).'</p>'; }
    $attachments=rows('SELECT * FROM attachments WHERE request_id=? OR act_id IN (SELECT act_id FROM allocations WHERE request_id=?) ORDER BY id',[$r['id'],$r['id']]);
    $html.='<h3>Índice de soportes</h3>'; if (!$attachments) $html.='<p>Solicitud sin soportes adjuntos.</p>'; foreach ($attachments as $a) $html.='<p>'.e($a['original_name']).'<br><small>SHA256: '.e($a['sha256']).'</small></p>';
    $pdf->writeHTML($html);
    foreach ($attachments as $a) {
        $path=attachmentPath($a);
        if ($a['mime']==='application/pdf') {
            $pages=$pdf->setSourceFile($path);
            for ($i=1;$i<=$pages;$i++) { $tpl=$pdf->importPage($i); $size=$pdf->getTemplateSize($tpl); $pdf->AddPage($size['orientation'],[$size['width'],$size['height']]); $pdf->useTemplate($tpl); }
        } else { $pdf->AddPage('P','A4'); $pdf->SetFont('dejavusans','',10); $pdf->MultiCell(174,10,$a['original_name']); $size=getimagesize($path); $w=min(174,($size[0]/$size[1])*235); $pdf->Image($path,18+(174-$w)/2,$pdf->GetY()+3,$w,min(235,174*$size[1]/$size[0]),$a['mime']==='image/png'?'PNG':'JPEG'); }
    }
    return $pdf->Output('solicitud.pdf','S');
}
