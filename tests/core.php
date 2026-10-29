<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/Admin.php';require ROOT.'/database/holidays.php';
installMysqlSchema(db());
require_once ROOT.'/app/LegacySqlite.php';
insert('departments',['name'=>'Dependencia de prueba']);
foreach ([['Funcionario','[]',''],['Jefe','["manager"]',''],['Subsecretario','["subsecretary","manager"]',''],['Alcalde','["mayor","manager"]',''],['Administrador','["admin","payroll"]',''],['Asesor','[]','Asesor de Despacho'],['Otro administrador','["admin","manager"]',''],['Otro funcionario','[]','']] as $i=>$p) insert('users',['name'=>$p[0],'identification'=>'PRUEBA-'.($i+1),'username'=>'qa'.($i+1),'email'=>'qa'.($i+1).'@ejemplo.invalid','password'=>password_hash('PruebaLocal!9876',PASSWORD_DEFAULT),'job_title'=>$p[0],'department_id'=>1,'manager_id'=>null,'roles'=>$p[1],'high_rank'=>$p[2],'force_password'=>0]);
run('UPDATE users SET manager_id=2');
setSetting('subsecretary_id','3');setSetting('mayor_id','4');
foreach(require ROOT.'/database/catalog.php' as $c)insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$c));
for($y=2026;$y<=2028;$y++)foreach(nationalHolidays($y)as $date=>$name)run('INSERT INTO holidays(date,name)VALUES(?,?) ON DUPLICATE KEY UPDATE name=name',[$date,$name]);
$wf=new Workflow();$checks=0;
function check(bool $condition,string $label):void{global $checks;ensure($condition,'FALLO: '.$label);$checks++;echo 'OK '.$label."\n";}
function refuses(callable $fn,string $label):void{try{$fn();}catch(DomainException){check(true,$label);return;}throw new RuntimeException('FALLO: '.$label);}
function person(int $id):array{return row('SELECT u.*,d.name department FROM users u JOIN departments d ON d.id=u.department_id WHERE u.id=?',[$id]);}
function cat(string $slug):array{return row('SELECT * FROM categories WHERE slug=?',[$slug]);}
function testSubmit(int $uid,string $slug,array $slots,array $allocations=[],?DateTimeImmutable $clock=null):int{
 global $wf;$u=person($uid);$c=cat($slug);
 $stub=insert('requests',['user_id'=>$uid,'category_id'=>$c['id'],'status'=>'returned','justification'=>'Solicitud de prueba','total_minutes'=>1,'snapshot'=>'{}','revision'=>0,'created_at'=>now(),'updated_at'=>now()]);
 insert('attachments',['user_id'=>$uid,'request_id'=>$stub,'original_name'=>'soporte-prueba.pdf','stored_name'=>'test-'.bin2hex(random_bytes(8)),'mime'=>'application/pdf','size'=>100,'sha256'=>str_repeat('0',64),'created_at'=>now()]);
 try{return $wf->submit($u,['category_id'=>$c['id'],'slots'=>$slots,'justification'=>'Justificación de prueba funcional','exception_reason'=>'Excepción justificada para probar el trámite','relationship'=>'Padre','allocations'=>$allocations],[],$stub,$clock??new DateTimeImmutable('2026-10-26 06:00:00'));}
 catch(Throwable $e){run('DELETE FROM attachments WHERE request_id=?',[$stub]);run('DELETE FROM requests WHERE id=?',[$stub]);throw $e;}
}
function slot(string $date,string $start,string $end):array{return ['date'=>$date,'start'=>$start,'end'=>$end,'mode'=>'hours'];}
$cal=$wf->calendar;
check($cal->minutes('2026-10-27','07:30','17:00')===510,'Jornada de lunes a jueves: 510 minutos');
check($cal->minutes('2026-10-30','07:00','16:00')===510,'Jornada del viernes: 510 minutos');
check($cal->minutes('2026-10-27','11:30','13:30')===60,'Almuerzo excluido entre semana');
check($cal->minutes('2026-10-30','11:30','13:00')===60,'Almuerzo del viernes de 30 minutos');
check($cal->periods('2026-10-31')===[]&&$cal->periods('2026-11-02')===[],'Exclusión de fin de semana y festivo trasladado');
check($cal->between(new DateTimeImmutable('2026-10-30 15:00'),new DateTimeImmutable('2026-11-03 08:00'))===90,'Tiempo laborable: excluye fin de semana y lunes festivo');
check($cal->between(new DateTimeImmutable('2026-10-26 07:30'),new DateTimeImmutable('2026-11-03 13:00'))===2820,'Intervalo de 2.820 minutos de jornada laboral');
check($cal->between(new DateTimeImmutable('2026-10-26 07:30'),new DateTimeImmutable('2026-11-03 14:00'))===2880,'Intervalo de 2.880 minutos de jornada laboral');
check(isset(nationalHolidays(2026)['2026-07-13']),'Festivo nacional de julio de 2026 trasladado');
check(array_column($wf->route(person(1),cat('ordinario'),120),'role')===['manager'],'Dos horas: solo jefe inmediato');
check(array_column($wf->route(person(1),cat('ordinario'),121),'role')===['manager','subsecretary'],'Más de dos horas: dos etapas');
check(array_column($wf->route(person(6),cat('ordinario'),60),'role')===['mayor'],'Alto rango: solo alcalde incluso permiso corto');
check(array_column($wf->route(person(6),cat('sufragar'),60),'role')===['manager','subsecretary'],'Alto rango: compensatorio mantiene dos etapas');
check(array_column($wf->route(person(6),cat('matrimonio'),60),'role')===['manager','subsecretary'],'Alto rango: licencia mantiene dos etapas');
refuses(fn()=>$wf->route(person(2),cat('ordinario'),60),'No permite autoaprobación sin suplente');
run('UPDATE users SET deputy_manager_id=7 WHERE id=2');check($wf->route(person(2),cat('ordinario'),60)[0]['assigned_user_id']===7,'Suplente explícito evita autoaprobación');
refuses(fn()=>$cal->slots([slot('2026-10-27','07:30','09:00'),slot('2026-10-27','08:00','10:00')],new DateTimeImmutable('2026-10-26')),'Rechaza franjas superpuestas');
$short=testSubmit(1,'ordinario',[slot('2026-11-03','07:30','09:30')]);
check($wf->quota(1,'2026-2')===120,'Permiso corto reserva el cupo semestral');
refuses(fn()=>$wf->review(person(3),$short,'approved',''),'Un aprobador posterior no puede saltarse la etapa');
refuses(fn()=>$wf->review(person(8),$short,'approved',''),'No permite decisiones de otro funcionario');
$wf->review(person(2),$short,'approved','Aprobación de prueba');check(row('SELECT status FROM requests WHERE id=?',[$short])['status']==='approved','Aprobación única concluye el permiso corto');
$long=testSubmit(1,'academico',[slot('2026-11-04','07:30','11:30')]);
$wf->review(person(2),$long,'approved','Primera etapa');check(row('SELECT status FROM requests WHERE id=?',[$long])['status']==='pending','Primera aprobación no concluye el trámite general');
$wf->review(person(3),$long,'returned','Adjuntar soporte corregido');check(row('SELECT status FROM requests WHERE id=?',[$long])['status']==='returned','Devolución para corrección');
$wf->submit(person(1),['category_id'=>cat('academico')['id'],'slots'=>[slot('2026-11-04','07:30','11:30')],'justification'=>'Solicitud con los datos corregidos','exception_reason'=>'Se requiere corrección para la prueba'],[],$long,new DateTimeImmutable('2026-10-26'));
check((int)row('SELECT revision FROM requests WHERE id=?',[$long])['revision']===2,'Reenvío conserva historial e inicia nueva revisión');
check(count(rows('SELECT * FROM approvals WHERE request_id=?',[$long]))===4,'Conserva etapas de ambas revisiones');
$q=testSubmit(8,'ordinario',[slot('2026-11-03','07:30','17:00'),slot('2026-11-04','07:30','17:00'),slot('2026-11-05','07:30','17:00')]);check($wf->quota(8,'2026-2')===1530,'Acepta exactamente tres días por semestre');
refuses(fn()=>testSubmit(8,'ordinario',[slot('2026-11-06','07:00','07:01')]),'Rechaza exceso de un minuto del cupo');
$wf->cancel(person(8),$q,'Cancelación de prueba');check($wf->quota(8,'2026-2')===0,'Cancelación antes de aprobación libera cupo');
refuses(fn()=>testSubmit(1,'ordinario',[slot('2027-01-05','07:30','08:00')]),'Ordinario solo en año en curso');
$act=insert('acts',['user_id'=>1,'number'=>'ACTO-QA','issued_on'=>'2026-01-01','expires_on'=>'2026-12-31','granted_minutes'=>510,'status'=>'pending','created_at'=>now()]);
refuses(fn()=>testSubmit(1,'acto',[slot('2026-11-06','07:00','10:00')],[['id'=>$act,'hours'=>'3']]),'Acto pendiente no ofrece saldo');
$wf->reviewAct(person(5),$act,'approved','Acto verificado');
$comp=testSubmit(1,'acto',[slot('2026-11-06','07:00','10:00')],[['id'=>$act,'hours'=>'3']]);check($wf->available(row('SELECT * FROM acts WHERE id=?',[$act]))===330,'Reserva de saldo al enviar');
refuses(fn()=>testSubmit(1,'acto',[slot('2026-11-09','07:30','14:30')],[['id'=>$act,'hours'=>'6']]),'No permite sobregirar saldo reservado');
$wf->review(person(2),$comp,'approved','Visto bueno');$wf->review(person(3),$comp,'approved','Autorizado');
check(row('SELECT state FROM allocations WHERE request_id=?',[$comp])['state']==='consumed','Descuento definitivo en aprobación final');
$wf->cancel(person(1),$comp,'Cancelación parcial para prueba');check(row('SELECT status FROM requests WHERE id=?',[$comp])['status']==='cancel_pending','Cancelación aprobada espera al subsecretario');
refuses(fn()=>$wf->reviewCancellation(person(2),$comp,true,'Sin competencia'),'Jefe no autoriza cancelación de aprobada');
$wf->reviewCancellation(person(3),$comp,true,'Autoriza reintegrar tiempo pendiente',new DateTimeImmutable('2026-11-06 08:00'));
check((int)row('SELECT returned_minutes FROM allocations WHERE request_id=?',[$comp])['returned_minutes']===120,'Reintegra solo 120 minutos no disfrutados');
check($wf->available(row('SELECT * FROM acts WHERE id=?',[$act]))===450,'El saldo conserva los 60 minutos disfrutados');
$expired=insert('acts',['user_id'=>1,'number'=>'VIGENCIA-QA','issued_on'=>'2026-01-01','expires_on'=>'2026-11-10','granted_minutes'=>510,'status'=>'approved','created_at'=>now()]);
refuses(fn()=>testSubmit(1,'acto',[slot('2026-11-12','07:30','08:30')],[['id'=>$expired,'hours'=>'1']]),'Vencimiento aplica a las fechas de disfrute');
$rejected=testSubmit(1,'acto',[slot('2026-11-13','07:00','08:00')],[['id'=>$act,'hours'=>'1']]);$wf->review(person(2),$rejected,'rejected','Soporte no válido');check($wf->available(row('SELECT * FROM acts WHERE id=?',[$act]))===450,'Rechazo libera reserva del acto');
$pending=testSubmit(1,'academico',[slot('2026-11-17','07:30','08:30')]);run('UPDATE users SET manager_id=7 WHERE id=1');atomic(fn()=>$wf->refreshAssignments(5));check((int)row("SELECT assigned_user_id FROM approvals WHERE request_id=? AND step=1",[$pending])['assigned_user_id']===2,'Cambio de jefe conserva las etapas pendientes con el responsable original');
check((int)row('SELECT acted_by FROM approvals WHERE request_id=?',[$short])['acted_by']===2,'Cambio de jefe conserva actuaciones históricas');
$temp=['name'=>'Extensión QA','start_date'=>'2026-12-01','end_date'=>'2026-12-02','periods'=>[]];for($d=1;$d<=5;$d++)$temp['periods'][$d]=[['07:30','12:00'],['13:00','18:00']];saveSchedule(person(5),$temp);check($cal->minutes('2026-12-01','07:30','18:00')===570,'Horario temporal amplía la jornada efectiva');
check($cal->minutes('2026-12-03','07:30','17:00')===510,'Fuera de vigencia se recupera horario base');
check(!canReadRequest(person(8),row('SELECT * FROM requests WHERE id=?',[$short])),'Otro funcionario no puede leer una solicitud ajena');
check(canReadRequest(person(2),row('SELECT * FROM requests WHERE id=?',[$short])),'Aprobador histórico conserva acceso al trámite');
$legacy=new PDO('sqlite::memory:');
$legacy->exec(file_get_contents(ROOT.'/database/legacy/sqlite-schema.sql'));
$legacy->exec("ALTER TABLE categories DROP COLUMN supports_required; INSERT INTO categories(family,name,slug,supports) VALUES('permiso','Tipo existente','existente','Soporte');");
migrateLegacySqlite($legacy);
$migrated=$legacy->query('SELECT * FROM categories')->fetch(PDO::FETCH_ASSOC);
check($migrated['name']==='Tipo existente' && (int)$migrated['supports_required']===1,'Migración conserva registros y obligatoriedad existente');
$legacy->exec('UPDATE categories SET supports_required=0');migrateLegacySqlite($legacy);
check((int)$legacy->query('SELECT supports_required FROM categories')->fetchColumn()===0,'Migración repetida conserva el soporte opcional');
foreach(['ordinario','bici','matrimonio','estudios'] as $i=>$slug) {
    $category=cat($slug);
    run('UPDATE categories SET supports_required=0 WHERE id=?',[$category['id']]);
    $input=['category_id'=>$category['id'],'slots'=>[slot('2026-12-'.(21+$i),'07:30','08:30')],'justification'=>'Solicitud de prueba sin soporte opcional'];
    $optional=$wf->submit(person(8),$input,[],null,new DateTimeImmutable('2026-10-26 06:00'));
    check((int)row('SELECT COUNT(*) n FROM attachments WHERE request_id=?',[$optional])['n']===0,'Permite enviar sin adjuntos cuando es opcional: '.$slug);
    run('UPDATE categories SET supports_required=1 WHERE id=?',[$category['id']]);
    $input['slots']=[slot('2026-12-'.(21+$i),'09:30','10:30')];
    refuses(fn()=>$wf->submit(person(8),$input,[],null,new DateTimeImmutable('2026-10-26 06:00')),'Exige adjunto al reactivar la obligatoriedad: '.$slug);
}

function approvalId(int $request,int $step=1):int{return (int)row('SELECT id FROM approvals WHERE request_id=? AND step=? ORDER BY revision DESC LIMIT 1',[$request,$step])['id'];}
$bulkA=testSubmit(8,'academico',[slot('2026-12-28','07:30','08:30')]);
$bulkB=testSubmit(8,'academico',[slot('2026-12-29','07:30','08:30')]);
$foreign=testSubmit(1,'academico',[slot('2026-12-31','07:30','08:30')]);
$batch=$wf->approveMany(person(2),[approvalId($bulkA),approvalId($bulkB),999999,approvalId($foreign)],'Observación común del lote');
check(count($batch['approved'])===2 && count($batch['failed'])===2,'Lote parcial: aprueba las válidas y reporta las ajenas o inexistentes');
check(row('SELECT status FROM requests WHERE id=?',[$bulkA])['status']==='pending','Lote del jefe conserva pendiente la aprobación del subsecretario');
check(str_contains(row("SELECT detail FROM events WHERE request_id=? AND action='Revisión: Aprobada'",[$bulkA])['detail'],'Aprobación masiva') && str_contains(row("SELECT detail FROM events WHERE request_id=? AND action='Revisión: Aprobada'",[$bulkA])['detail'],'Observación común del lote'),'Cada aprobación masiva conserva su constancia y observación');
$batch=$wf->approveMany(person(3),[approvalId($bulkA,2),approvalId($bulkB,2)]);
check(count($batch['approved'])===2 && row('SELECT status FROM requests WHERE id=?',[$bulkB])['status']==='approved','Subsecretario concluye varias solicitudes en su etapa');
$repeat=$wf->approveMany(person(3),[approvalId($bulkA,2)]);
check(count($repeat['failed'])===1 && (int)row("SELECT COUNT(*) n FROM events WHERE request_id=? AND action='Revisión: Aprobada'",[$bulkA])['n']===2,'Reenvío del lote no duplica decisiones ni constancias');
$early=testSubmit(8,'academico',[slot('2026-12-28','09:00','10:00')]);
check(count($wf->approveMany(person(3),[approvalId($early,2)])['failed'])===1,'Lote del subsecretario no puede saltar la etapa del jefe');
$shortBulk=testSubmit(8,'ordinario',[slot('2026-12-30','07:30','08:30')]);
$duplicate=$wf->approveMany(person(2),[approvalId($shortBulk),approvalId($shortBulk)]);
check(count($duplicate['approved'])===1 && row('SELECT status FROM requests WHERE id=?',[$shortBulk])['status']==='approved','Lote deduplica selecciones y concluye el permiso corto');
$mayoral=testSubmit(6,'academico',[slot('2026-12-28','07:30','08:30')]);
check(count($wf->approveMany(person(4),[approvalId($mayoral)])['failed'])===1 && row('SELECT status FROM requests WHERE id=?',[$mayoral])['status']==='pending','Un usuario con rol de alcalde y jefe no aprueba etapas del alcalde masivamente');
refuses(fn()=>$wf->approveMany(person(8),[approvalId($early)]),'Solicitante no puede aprobar masivamente');
refuses(fn()=>$wf->approveMany(person(5),[approvalId($early)]),'Administrador sin rol aprobador no puede aprobar masivamente');
refuses(fn()=>$wf->approveMany(person(2),[]),'Lote vacío se rechaza');
refuses(fn()=>$wf->approveMany(person(2),range(1,101)),'Lote mayor de cien se rechaza');
refuses(fn()=>$wf->approveMany(person(2),[approvalId($early),'etapa-inválida']),'Selección malformada se rechaza antes de aprobar cualquier solicitud');
check(row('SELECT status FROM approvals WHERE id=?',[approvalId($early)])['status']==='waiting','Lote malformado no altera solicitudes válidas incluidas');
$bulkComp=testSubmit(1,'acto',[slot('2026-12-28','13:00','14:00')],[['id'=>$act,'hours'=>'1']]);
$wf->approveMany(person(7),[approvalId($bulkComp)]);
check(row('SELECT state FROM allocations WHERE request_id=?',[$bulkComp])['state']==='reserved','Primera etapa masiva conserva la reserva del acto');
$wf->approveMany(person(3),[approvalId($bulkComp,2)]);
check(row('SELECT state FROM allocations WHERE request_id=?',[$bulkComp])['state']==='consumed','Aprobación final masiva descuenta definitivamente el acto');
run('UPDATE users SET manager_id=3 WHERE id=1');
$dual=testSubmit(1,'academico',[slot('2026-12-30','10:00','11:00')]);
$firstDual=approvalId($dual);$wf->approveMany(person(3),[$firstDual]);$stale=$wf->approveMany(person(3),[$firstDual]);
check(count($stale['failed'])===1 && row('SELECT status FROM requests WHERE id=?',[$dual])['status']==='pending','Un doble envío no aprueba la etapa siguiente aunque el usuario tenga ambos roles');
$dual2=testSubmit(1,'academico',[slot('2026-12-31','10:00','11:00')]);
$dualBatch=$wf->approveMany(person(3),[approvalId($dual2),approvalId($dual2,2)]);
check(count($dualBatch['approved'])===1 && count($dualBatch['failed'])===1 && row('SELECT status FROM requests WHERE id=?',[$dual2])['status']==='pending','Un lote permite como máximo una etapa por solicitud');


check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26 08:00'),new DateTimeImmutable('2026-10-28 08:00'))===2880,'Anticipación: lunes a miércoles a la misma hora suma 48 horas calendario hábiles');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26 08:00'),new DateTimeImmutable('2026-10-28 07:59'))===2879,'Anticipación: un minuto antes del umbral no completa 48 horas');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26 08:00'),new DateTimeImmutable('2026-10-28 07:59:59'))===2879,'Anticipación: un segundo antes del umbral no completa 48 horas');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26 12:00'),new DateTimeImmutable('2026-10-26 13:00'))===60,'Anticipación incluye el almuerzo de un día hábil');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26 17:00'),new DateTimeImmutable('2026-10-27 07:30'))===870,'Anticipación incluye la noche entre días hábiles');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-30 08:00'),new DateTimeImmutable('2026-11-03 08:00'))===1440,'Anticipación excluye sábado domingo y lunes festivo completos');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-30 08:00'),new DateTimeImmutable('2026-11-04 08:00'))===2880,'Anticipación alcanza 48 horas tras un puente festivo');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-31 08:00'),new DateTimeImmutable('2026-11-04 08:00'))===1920,'Solicitud registrada en fin de semana comienza a contar en el siguiente día hábil');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-30 23:30'),new DateTimeImmutable('2026-11-03 00:30'))===60,'Anticipación suma fracciones de días hábiles alrededor de medianoche');
check($cal->noticeMinutes(new DateTimeImmutable('2026-12-01 00:00'),new DateTimeImmutable('2026-12-03 00:00'))===2880,'Los horarios temporales no limitan las horas calendario de anticipación');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-26T13:00:00Z'),new DateTimeImmutable('2026-10-28T13:00:00Z'))===2880,'Anticipación usa la zona horaria de Bogotá');
check($cal->noticeMinutes(new DateTimeImmutable('2026-10-28 08:00'),new DateTimeImmutable('2026-10-26 08:00'))===0,'Anticipación no devuelve duraciones negativas');
$noticeCategory=cat('academico');run('UPDATE categories SET supports_required=0 WHERE id=?',[$noticeCategory['id']]);
$noticeInput=['category_id'=>$noticeCategory['id'],'slots'=>[slot('2026-11-11','07:30','08:30')],'justification'=>'Solicitud con dos días hábiles completos de anticipación'];
$noticeRequest=$wf->submit(person(8),$noticeInput,[],null,new DateTimeImmutable('2026-11-09 07:30'));
check(row('SELECT status FROM requests WHERE id=?',[$noticeRequest])['status']==='pending','Solicitud con 48 horas calendario hábiles se envía sin excepción');
$noticeInput['slots']=[slot('2026-11-11','09:00','10:00')];
refuses(fn()=>$wf->submit(person(8),$noticeInput,[],null,new DateTimeImmutable('2026-11-09 09:01')),'Solicitud con menos de 48 horas calendario hábiles exige excepción');
$noticeInput['exception_reason']='Justificación de excepción de anticipación para prueba';
$noticeException=$wf->submit(person(8),$noticeInput,[],null,new DateTimeImmutable('2026-11-09 09:01'));
check(row('SELECT status FROM requests WHERE id=?',[$noticeException])['status']==='pending','Se conserva el envío fuera de plazo con excepción justificada');
$noticeInput['slots']=[slot('2026-11-09','07:30','08:30')];unset($noticeInput['exception_reason']);
refuses(fn()=>$wf->submit(person(8),$noticeInput,[],null,new DateTimeImmutable('2026-11-06 07:30')),'Un fin de semana no completa por sí solo la anticipación requerida');
run('UPDATE categories SET supports_required=1 WHERE id=?',[$noticeCategory['id']]);

require __DIR__.'/designations.php';
require __DIR__.'/catalog-bulk.php';
require __DIR__.'/ongoing.php';
require __DIR__.'/migrations.php';
require __DIR__.'/mysql.php';
echo "\n$checks comprobaciones correctas.\n";
file_put_contents(ROOT.'/.work/test-result.json',json_encode(['checks'=>$checks,'database'=>$GLOBALS['config']['mysql']['database'],'database_config'=>$GLOBALS['test_configuration_file'],'engine'=>db()->query('SELECT VERSION()')->fetchColumn(),'completed'=>now()],JSON_PRETTY_PRINT));
