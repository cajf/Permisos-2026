<?php
declare(strict_types=1);
$oldDatabase=new PDO('sqlite::memory:');$oldDatabase->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$oldDatabase->exec(file_get_contents(__DIR__.'/fixtures/schema-before-designations.sql'));
$oldDatabase->exec("INSERT INTO departments(id,name) VALUES(1,'Anterior');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id,roles) VALUES(2,'Jefe anterior','ANT-2','anterior2','jefe@ejemplo.invalid','hash','Jefe',1,NULL,'[\"manager\"]');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id,roles) VALUES(3,'Subsecretario anterior','ANT-3','anterior3','sub@ejemplo.invalid','hash','Subsecretario',1,2,'[\"subsecretary\"]');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id) VALUES(1,'Titular anterior','ANT-1','anterior1','titular@ejemplo.invalid','hash','Funcionario',1,2);
INSERT INTO categories(id,family,name,slug,supports,supports_required) VALUES(1,'permiso','Académico','academico','Soporte',0);
INSERT INTO requests(id,user_id,category_id,justification,total_minutes,snapshot,created_at,updated_at) VALUES(1,1,1,'Solicitud previa',60,'{}','2026-10-26 08:00:00','2026-10-26 08:00:00');
INSERT INTO request_slots(request_id,date,start_time,end_time,minutes,segments,semester) VALUES(1,'2026-11-20','07:00','08:00',60,'[[\"07:00\",\"08:00\"]]','2026-2');
INSERT INTO approvals(request_id,revision,step,role,assigned_user_id) VALUES(1,1,1,'manager',2);
INSERT INTO approvals(request_id,revision,step,role,assigned_user_id) VALUES(1,1,2,'subsecretary',3);");
migrateDatabase($oldDatabase);migrateDatabase($oldDatabase);
check((int)$oldDatabase->query('SELECT assigned_user_id FROM approvals WHERE step=1')->fetchColumn()===2,'La migración real conserva al jefe de la etapa pendiente anterior');
check($oldDatabase->query('SELECT start_at FROM request_slots')->fetchColumn()==='2026-11-20 07:00:00','La migración incorpora fechas y horas completas sin alterar los minutos existentes');
check((int)$oldDatabase->query('SELECT supports_required FROM categories')->fetchColumn()===0,'La migración completa conserva los soportes opcionales configurados');
check((int)$oldDatabase->query('SELECT COUNT(*) FROM designations')->fetchColumn()===0,'La migración no fabrica designaciones históricas');
check($oldDatabase->query('PRAGMA foreign_key_check')->fetchAll()===[],'La actualización del esquema anterior conserva su integridad referencial');

$retiredDatabase=new PDO('sqlite::memory:');$retiredDatabase->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$retiredDatabase->exec(file_get_contents(__DIR__.'/fixtures/schema-with-special-schedules.sql'));
$retiredDatabase->exec('BEGIN; PRAGMA defer_foreign_keys=ON;');
foreach (['departments','users','categories','requests','request_slots','approvals'] as $table) foreach ($oldDatabase->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC) as $record) {
    $query=$retiredDatabase->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($record)).') VALUES ('.implode(',',array_fill(0,count($record),'?')).')');$query->execute(array_values($record));
}
$retiredDatabase->exec('COMMIT');
$legacySegments=json_encode([['2026-11-20 22:00:00','2026-11-21 02:00:00'],['2026-11-21 02:30:00','2026-11-21 06:30:00']]);
$legacyPeriods=json_encode([['start'=>'22:00','start_day'=>0,'end'=>'02:00','end_day'=>1],['start'=>'02:30','start_day'=>1,'end'=>'06:30','end_day'=>1]]);
$query=$retiredDatabase->prepare("UPDATE requests SET special_note=?,total_minutes=480,snapshot=?,status='returned' WHERE id=1");
$query->execute(['Nota de jornada registrada antes del retiro',json_encode(['name'=>'Titular anterior','custom'=>'Se conserva','archived_data'=>['previous'=>'Registro anterior']])]);
$query=$retiredDatabase->prepare("UPDATE request_slots SET start_time='22:00',end_time='06:30',start_at='2026-11-20 22:00:00',end_at='2026-11-21 06:30:00',minutes=480,segments=?,special_periods=?");$query->execute([$legacySegments,$legacyPeriods]);
$retiredDatabase->exec("UPDATE categories SET notes='Nota administrativa personalizada' WHERE slug='academico'");
migrateDatabase($retiredDatabase);migrateDatabase($retiredDatabase);
check(!in_array('special_note',array_column($retiredDatabase->query('PRAGMA table_info(requests)')->fetchAll(PDO::FETCH_ASSOC),'name'),true) && !in_array('special_periods',array_column($retiredDatabase->query('PRAGMA table_info(request_slots)')->fetchAll(PDO::FETCH_ASSOC),'name'),true),'La migración elimina las columnas exclusivas del horario retirado');
$archivedRequest=$retiredDatabase->query('SELECT * FROM requests WHERE id=1')->fetch(PDO::FETCH_ASSOC);$archivedSnapshot=json_decode($archivedRequest['snapshot'],true);
check($archivedSnapshot['archived_data']['schedule_before_removal']['note']==='Nota de jornada registrada antes del retiro' && $archivedSnapshot['archived_data']['schedule_before_removal']['slots'][0]['special_periods']===$legacyPeriods,'Los datos retirados quedan resguardados en la copia histórica');
check($archivedSnapshot['custom']==='Se conserva' && $archivedSnapshot['archived_data']['previous']==='Registro anterior','El archivo conserva los datos del funcionario y archivos históricos previos');
$archivedSlot=$retiredDatabase->query('SELECT * FROM request_slots')->fetch(PDO::FETCH_ASSOC);
check((int)$archivedRequest['total_minutes']===480 && $archivedSlot['segments']===$legacySegments && $archivedRequest['status']==='returned','El retiro no recalcula minutos, segmentos ni estado de solicitudes anteriores');
check((int)$retiredDatabase->query('SELECT assigned_user_id FROM approvals WHERE step=1')->fetchColumn()===2 && $retiredDatabase->query('PRAGMA foreign_key_check')->fetchAll()===[],'Se mantienen los aprobadores y la integridad de los registros anteriores');
check($wf->consumedSlot($archivedSlot,'2026-11-21 03:00:00')===270,'La cancelación de un registro anterior sigue usando sus minutos históricos');
check($retiredDatabase->query("SELECT notes FROM categories WHERE slug='academico'")->fetchColumn()==='Nota administrativa personalizada','El retiro no modifica las reglas de otros tipos');

// Al corregir una solicitud se conserva el archivo técnico, pero se vuelve a solicitar con la jornada configurada.
$correction=testSubmit(9,'academico',[slot('2026-12-10','07:30','08:30')],[],new DateTimeImmutable('2026-10-27 06:00'));
$wf->review(person(11),$correction,'returned','Corrija el motivo de su solicitud');
$snapshot=row('SELECT snapshot FROM requests WHERE id=?',[$correction]);$snapshotData=json_decode($snapshot['snapshot'],true);$snapshotData['archived_data']=$archivedSnapshot['archived_data'];
run('UPDATE requests SET snapshot=? WHERE id=?',[json_encode($snapshotData),$correction]);
$wf->submit(person(9),['category_id'=>cat('academico')['id'],'slots'=>[slot('2026-12-10','09:00','10:00')],'justification'=>'Solicitud corregida con jornada laboral configurada'],[],$correction,new DateTimeImmutable('2026-10-27 06:00'));
check(json_decode(row('SELECT snapshot FROM requests WHERE id=?',[$correction])['snapshot'],true)['archived_data']===$archivedSnapshot['archived_data'],'La corrección conserva la copia histórica sin reactivar el horario retirado');
