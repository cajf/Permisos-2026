<?php
declare(strict_types=1);

function laborRecordFilters(array $input): array {
    $from=$input['from']??date('Y-m-01');
    $to=$input['to']??date('Y-m-d');
    $person=$input['person']??'';
    ensure(is_string($from) && is_string($to) && validDate($from) && validDate($to) && $from<=$to,
        'Seleccione un rango válido de fechas de registro.');
    ensure(is_string($person) && mb_strlen($person)<=180,'El nombre o la cédula supera el límite de búsqueda.');
    return ['from'=>$from,'to'=>$to,'person'=>trim($person)];
}

function laborRecordQuery(array $filters,?int $id=null,?int $page=null): PDOStatement {
    $sql="SELECT r.*,p.name AS person_name,p.identification,c.name AS category,c.family
        FROM requests r JOIN persons p ON p.id=r.user_id JOIN categories c ON c.id=r.category_id
        WHERE r.created_at>=? AND r.created_at<=? AND r.status IN ('approved','cancel_pending')
        AND EXISTS (SELECT 1 FROM approvals a WHERE a.request_id=r.id AND a.revision=r.revision
            AND a.status='approved' AND a.step=(SELECT MAX(b.step) FROM approvals b WHERE b.request_id=r.id AND b.revision=r.revision))";
    $params=[$filters['from'].' 00:00:00',$filters['to'].' 23:59:59'];
    if($filters['person']!=='') {
        $sql.=' AND (p.identification=? OR LOCATE(?,p.name COLLATE utf8mb4_0900_ai_ci)>0)';
        array_push($params,$filters['person'],$filters['person']);
    }
    if($id!==null) {
        $sql.=' AND r.id=?';
        $params[]=$id;
    }
    $sql.=' ORDER BY r.created_at DESC,r.id DESC';
    if($page!==null) $sql.=' LIMIT 26 OFFSET '.(25*min(100000,max(0,$page-1)));
    $statement=db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function laborRecordPdf(array $request): string {
    try {
        return requestPdf($request);
    } catch (Throwable $error) {
        error_log((string)$error);
        throw new DomainException(sprintf('No se pudo generar la constancia TH-%06d con todos sus soportes.', $request['id']));
    }
}

function downloadLaborRecords(array $actor,array $filters,?int $id): never {
    requirePermission($actor,'view.labor_records');
    requirePermission($actor,'resource.labor_records');
    $requests=laborRecordQuery($filters,$id);
    $request=$requests->fetch();
    ensure($request!==false,'No hay solicitudes aprobadas que coincidan con esta descarga.');
    header('Cache-Control: no-store, private');
    session_write_close();
    if($id!==null) {
        $pdf=laborRecordPdf($request);
        event((int)$actor['id'],'Descarga individual de Historias Laborales',sprintf('TH-%06d',$id));
        header('Content-Type: application/pdf');
        header(sprintf('Content-Disposition: attachment; filename="TH-%06d.pdf"',$id));
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        exit;
    }

    $directory=sys_get_temp_dir().'/th-records-'.bin2hex(random_bytes(16));
    ensure(mkdir($directory,0700),'No se pudo preparar la descarga masiva.');
    $archive=$directory.'/constancias.zip';
    $files=[];
    $ids=[];
    $zip=new ZipArchive();
    $opened=false;
    try {
        ensure($zip->open($archive,ZipArchive::CREATE|ZipArchive::EXCL)===true,'No se pudo crear el ZIP.');
        $opened=true;
        do {
            $radicado=sprintf('TH-%06d',$request['id']);
            $path=$directory.'/'.$radicado.'.pdf';
            $files[]=$path;
            $pdf=laborRecordPdf($request);
            ensure(file_put_contents($path,$pdf)===strlen($pdf),'No se pudo preparar el PDF '.$radicado.'.');
            unset($pdf);
            $folder=sprintf('Funcionario-%d',$request['user_id']);
            ensure($zip->addFile($path,$folder.'/'.$radicado.'.pdf'),'No se pudo incluir '.$radicado.' en el ZIP.');
            $ids[]=$radicado;
        } while($request=$requests->fetch());
        $closed=$zip->close();
        $opened=false;
        ensure($closed,'No se pudo finalizar el ZIP.');
        event((int)$actor['id'],'Descarga masiva de Historias Laborales',
            json_encode(['desde'=>$filters['from'],'hasta'=>$filters['to'],'radicados'=>$ids],JSON_THROW_ON_ERROR));
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="historias-laborales-'.$filters['from'].'-'.$filters['to'].'.zip"');
        header('Content-Length: '.filesize($archive));
        readfile($archive);
    } finally {
        if($opened) $zip->close();
        foreach($files as $path) if(is_file($path)) unlink($path);
        if(is_file($archive)) unlink($archive);
        rmdir($directory);
    }
    exit;
}
