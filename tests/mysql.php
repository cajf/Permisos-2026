<?php
declare(strict_types=1);
require ROOT.'/app/Transfer.php';require ROOT.'/app/Backup.php';
check(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' && stripos(db()->query('SELECT VERSION()')->fetchColumn(),'mariadb')===false,'Las pruebas se ejecutan en MySQL real');
check((int)row("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND ENGINE!='InnoDB'")['n']===0,'Todas las tablas de la aplicación usan InnoDB');
refuses(fn()=>atomic(function(){setSetting('rollback_probe','no debe persistir');throw new DomainException('Prueba de reversión');}),'Una operación fallida revierte su transacción MySQL');
check(setting('rollback_probe')==='','La reversión no deja cambios parciales');
try {insert('users',['name'=>'Usuario inválido','identification'=>'FK-INVALID','username'=>'fk.invalid','email'=>'fk@ejemplo.invalid','password'=>'hash','job_title'=>'Prueba','department_id'=>999999]);throw new RuntimeException('No se aplicó la clave foránea.');}catch(PDOException){check(true,'MySQL impide crear funcionarios con dependencia inexistente');}

function concurrentJobs(array $jobs):array {
    $stem=ROOT.'/.work/race-'.bin2hex(random_bytes(5));$processes=[];
    foreach($jobs as $index=>$job){$job['go']=$stem.'.go';$job['ready']=$stem.'-'.$index.'.ready';$pipes=[];
        $process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),ROOT.'/tests/mysql-worker.php',base64_encode(json_encode($job))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT);
        if(!is_resource($process))throw new RuntimeException('No se pudo iniciar el trabajador de prueba.');fclose($pipes[0]);$processes[]=[$process,$pipes,$job['ready']];
    }
    $deadline=microtime(true)+15;
    while(count(array_filter($processes,fn($item)=>is_file($item[2])))!==count($processes)){if(microtime(true)>$deadline){foreach($processes as $item)proc_terminate($item[0]);throw new RuntimeException('Los trabajadores no llegaron a la barrera.');}usleep(10000);}
    file_put_contents($stem.'.go','go');$results=[];
    foreach($processes as [$process,$pipes,$ready]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException('Falló el trabajador: '.$errors);$results[]=json_decode($output,true,512,JSON_THROW_ON_ERROR);}
    return $results;
}
$raceUser=insert('users',['name'=>'Funcionario de concurrencia','identification'=>'RACE-QA','username'=>'race.qa','email'=>'race@ejemplo.invalid','password'=>'hash','job_title'=>'Funcionario','department_id'=>2,'manager_id'=>2,'birth_date'=>'1988-06-20']);
foreach(['ordinario','acto','cumpleanos','academico'] as $slug)run('UPDATE categories SET supports_required=0 WHERE slug=?',[$slug]);
$base=['justification'=>'Solicitud simultánea para verificar los límites transaccionales'];
$quotaJobs=[];foreach([['2026-12-21','2026-12-22'],['2026-12-23','2026-12-24']] as $dates)$quotaJobs[]=['action'=>'submit','actor'=>$raceUser,'input'=>$base+['category_id'=>cat('ordinario')['id'],'slots'=>array_map(fn($date)=>['date'=>$date,'mode'=>'full'],$dates)]];
$results=concurrentJobs($quotaJobs);
check(count(array_filter($results,fn($result)=>$result['ok']))===1 && $wf->quota($raceUser,'2026-2')===1020,'Dos solicitudes simultáneas no pueden exceder el cupo semestral');
$raceAct=insert('acts',['user_id'=>14,'number'=>'CONCURRENCIA-SALDO','issued_on'=>date('Y-m-d'),'expires_on'=>'2026-12-31','granted_minutes'=>60,'status'=>'approved','reviewed_by'=>12,'reviewed_at'=>now(),'created_at'=>now()]);
$actJobs=[];foreach(['2026-12-16','2026-12-17'] as $date)$actJobs[]=['action'=>'submit','actor'=>14,'input'=>$base+['category_id'=>cat('acto')['id'],'slots'=>[slot($date,'13:00','14:00')],'allocations'=>[['id'=>$raceAct,'hours'=>'1']]]];
$results=concurrentJobs($actJobs);
check(count(array_filter($results,fn($result)=>$result['ok']))===1 && $wf->available(row('SELECT * FROM acts WHERE id=?',[$raceAct]))===0,'Dos solicitudes simultáneas no pueden reservar las mismas horas de un acto');
$birthdayJobs=[];foreach(['2026-12-14','2026-12-15'] as $date)$birthdayJobs[]=['action'=>'submit','actor'=>$raceUser,'input'=>$base+['category_id'=>cat('cumpleanos')['id'],'birthday_year'=>'2026','slots'=>[['date'=>$date,'mode'=>'full']]]];
$results=concurrentJobs($birthdayJobs);
check(count(array_filter($results,fn($result)=>$result['ok']))===1,'Dos solicitudes simultáneas no duplican el beneficio anual de Cumpleaños');
$raceRequest=$wf->submit(person($raceUser),$base+['category_id'=>cat('academico')['id'],'slots'=>[slot('2026-12-10','13:00','14:00')]]);
$results=concurrentJobs([['action'=>'review','actor'=>11,'request'=>$raceRequest],['action'=>'review','actor'=>11,'request'=>$raceRequest]]);
check(count(array_filter($results,fn($result)=>$result['ok']))===1 && (int)row("SELECT COUNT(*) n FROM approvals WHERE request_id=? AND role='manager' AND status='approved'",[$raceRequest])['n']===1,'Dos aprobaciones simultáneas registran una única decisión por etapa');
foreach(['ordinario','acto','cumpleanos','academico'] as $slug)run('UPDATE categories SET supports_required=1 WHERE slug=?',[$slug]);

$adminConfiguration=require ROOT.'/storage/mysql/admin.php';$admin=mysqlConnection($adminConfiguration,false);
$transferName='permisos_test_transfer_'.bin2hex(random_bytes(4));$restoreName='permisos_test_restore_'.bin2hex(random_bytes(4));
foreach([$transferName,$restoreName] as $database)$admin->exec('CREATE DATABASE '.mysqlIdentifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin');
$target=mysqlConnection(array_replace($adminConfiguration,['database'=>$transferName]));installMysqlSchema($target);
$sourceUploads=ROOT.'/.work/transfer-'.bin2hex(random_bytes(4));mkdir($sourceUploads);$fileName=bin2hex(random_bytes(24));file_put_contents($sourceUploads.'/'.$fileName,"Soporte íntegro de migración\n");
$statement=$oldDatabase->prepare('INSERT INTO attachments(user_id,request_id,original_name,stored_name,mime,size,sha256,created_at) VALUES(1,1,?,?,?,?,?,?)');$statement->execute(['soporte.txt',$fileName,'application/pdf',filesize($sourceUploads.'/'.$fileName),hash_file('sha256',$sourceUploads.'/'.$fileName),now()]);
$badChecksum=$oldDatabase->prepare('UPDATE attachments SET sha256=?');$badChecksum->execute([str_repeat('0',64)]);
try{transferSqliteToMysql($oldDatabase,$target,$sourceUploads,$sourceUploads.'-target');throw new RuntimeException('Se importó un soporte alterado.');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'integridad') && (int)$target->query('SELECT COUNT(*) FROM users')->fetchColumn()===0,'Un soporte alterado bloquea la migración y no deja datos parciales');}
$badChecksum->execute([hash_file('sha256',$sourceUploads.'/'.$fileName)]);
$result=transferSqliteToMysql($oldDatabase,$target,$sourceUploads,$sourceUploads.'-target');
check($result['tables']['users']===3 && $result['tables']['approvals']===2 && $result['files_verified']===1 && $result['files_copied']===1,'La migración a MySQL conserva usuarios, aprobaciones y soportes con verificación');
try{transferSqliteToMysql($oldDatabase,$target,$sourceUploads,$sourceUploads.'-target');throw new RuntimeException('Se sobrescribió el destino.');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'vacía'),'La migración impide sobrescribir una base MySQL con registros');}
$backupDirectory=ROOT.'/.work/mysql-backup-'.bin2hex(random_bytes(4));$manifest=createMysqlBackup($target,$sourceUploads.'-target',$backupDirectory);
$restored=mysqlConnection(array_replace($adminConfiguration,['database'=>$restoreName]),true,true);restoreMysqlBackup($restored,$backupDirectory,$sourceUploads.'-restored');
check((int)$restored->query('SELECT COUNT(*) FROM users')->fetchColumn()===3 && hash_file('sha256',$sourceUploads.'-restored/'.$fileName)===$manifest['files'][$fileName],'El respaldo MySQL se restaura con conteos e integridad de soportes verificados');
try{restoreMysqlBackup($restored,$backupDirectory,$sourceUploads.'-restored');throw new RuntimeException('Se sobrescribió una restauración.');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'vacía'),'La restauración impide reemplazar una base existente');}
verifyMysqlReferences(db());check(true,'Las pruebas dejan todas las referencias MySQL válidas');
