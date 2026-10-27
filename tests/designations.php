<?php
declare(strict_types=1);
// Se ejecuta desde core.php sobre su base aislada; no modifica el directorio real.
$departmentA=insert('departments',['name'=>'Dependencia con relevo QA']);
$departmentB=insert('departments',['name'=>'Segunda dependencia QA']);
foreach ([['qa9','Funcionario diciembre','[]','1988-12-20'],['qa10','Jefe saliente','["manager"]',null],['qa11','Jefe entrante','["manager"]',null],['qa12','Nómina QA','["payroll"]',null],['qa13','Subsecretario entrante','["subsecretary"]',null],['qa14','Funcionario junio','[]','1988-06-20']] as $p) insert('users',['username'=>$p[0],'name'=>$p[1],'identification'=>'ADICIONAL-'.$p[0],'email'=>$p[0].'@ejemplo.invalid','password'=>password_hash('PruebaLocal!9876',PASSWORD_DEFAULT),'job_title'=>$p[1],'department_id'=>$departmentA,'manager_id'=>2,'roles'=>$p[2],'birth_date'=>$p[3],'force_password'=>0]);
$today=date('Y-m-d');$tomorrow=date('Y-m-d',strtotime('+1 day'));
refuses(fn()=>saveDesignation(person(8),['role'=>'manager','user_id'=>10,'start_date'=>$today,'department_ids'=>[$departmentA]]),'Solo Talento Humano registra designaciones');
saveDesignation(person(5),['role'=>'manager','user_id'=>10,'alternate_id'=>7,'start_date'=>$today,'department_ids'=>[$departmentA,$departmentB]]);
check((int)designation('manager',$departmentA)['user_id']===10 && (int)designation('manager',$departmentB)['user_id']===10,'Una designación asigna el jefe a varias dependencias');
check($wf->approver('manager',person(9))===10 && $wf->approver('manager',person(10))===7,'La dependencia determina el jefe y el suplente evita autoaprobación');
saveDesignation(person(5),['role'=>'subsecretary','user_id'=>3,'alternate_id'=>13,'start_date'=>$today]);
$before=testSubmit(9,'academico',[slot('2026-12-04','07:00','08:00')],[],new DateTimeImmutable($today.' 06:00'));
saveDesignation(person(5),['role'=>'manager','user_id'=>11,'alternate_id'=>7,'start_date'=>$tomorrow,'department_ids'=>[$departmentA,$departmentB]]);
saveDesignation(person(5),['role'=>'subsecretary','user_id'=>13,'alternate_id'=>3,'start_date'=>$tomorrow]);
check(designation('manager',$departmentA)['end_date']===$today && $wf->approver('manager',person(9))===10,'El jefe programado no reemplaza al vigente antes de su fecha');
check($wf->approver('manager',person(9),$tomorrow)===11 && $wf->approver('subsecretary',person(9),$tomorrow)===13,'Las nuevas designaciones se activan por fecha sin cambiar cada funcionario');
$after=testSubmit(9,'academico',[slot('2026-12-04','09:00','10:00')],[],new DateTimeImmutable($tomorrow.' 06:00'));
refuses(fn()=>$wf->review(person(11),$after,'approved','Intento anticipado'),'El jefe futuro no puede aprobar antes del inicio de su designación');
refuses(fn()=>$wf->review(person(13),$before,'approved','Otra etapa'),'El subsecretario entrante no recibe las etapas anteriores');
// Simula el cambio de fecha del sistema sin alterar el reloj de la máquina.
run('UPDATE designations SET start_date=?,end_date=? WHERE user_id=10',[(new DateTimeImmutable($today))->modify('-2 days')->format('Y-m-d'),(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d')]);
run('UPDATE designations SET start_date=? WHERE user_id=11',[$today]);
run("UPDATE designations SET start_date=?,end_date=? WHERE role='subsecretary' AND user_id=3",[(new DateTimeImmutable($today))->modify('-2 days')->format('Y-m-d'),(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d')]);
run("UPDATE designations SET start_date=? WHERE role='subsecretary' AND user_id=13",[$today]);
$wf->review(person(10),$before,'approved','Concluye primera etapa anterior');
$wf->review(person(3),$before,'approved','Concluye trámite recibido antes del relevo');
check(row('SELECT status FROM requests WHERE id=?',[$before])['status']==='approved','Los responsables anteriores terminan sus trámites tras finalizar su vigencia');
$wf->review(person(11),$after,'approved','Jefe entrante');$wf->review(person(13),$after,'approved','Subsecretario entrante');
check(row('SELECT status FROM requests WHERE id=?',[$after])['status']==='approved','La radicación nueva conserva a los nuevos responsables');
check(balanceScope(person(10),$today)['departments']===[] && in_array($departmentA,balanceScope(person(10),date('Y-m-d',strtotime('-1 day')))['departments'],true),'El jefe anterior solo consulta saldos dentro de las fechas de su periodo');
check(!in_array($departmentA,balanceScope(person(8),$today)['departments'],true),'Un funcionario ajeno no accede a saldos de la dependencia');
check((int)row("SELECT COUNT(*) n FROM events WHERE action='Designación registrada' AND actor_id=5")['n']===6,'Cada cambio conserva usuario, fecha y hora en auditoría');
refuses(fn()=>saveDesignation(person(5),['role'=>'manager','user_id'=>11,'start_date'=>date('Y-m-d',strtotime('-1 day')),'department_ids'=>[$departmentA]]),'No se registran relevos retroactivos');
refuses(fn()=>saveDesignation(person(5),['role'=>'manager','user_id'=>11,'alternate_id'=>11,'start_date'=>$tomorrow,'department_ids'=>[$departmentA]]),'Una designación no admite al mismo responsable como suplente');
refuses(fn()=>saveDesignation(person(5),['role'=>'manager','user_id'=>11,'start_date'=>$tomorrow,'department_ids'=>[$departmentA,99999]]),'Una asignación múltiple con dependencia inválida se revierte completa');
check(designation('manager',$departmentA,$tomorrow)['start_date']===$today,'La transacción fallida no cierra ni reemplaza el periodo actual');

$payrollAct=insert('acts',['user_id'=>9,'number'=>'NOMINA-QA','issued_on'=>$today,'expires_on'=>'2027-12-31','granted_minutes'=>510,'created_at'=>now()]);
refuses(fn()=>$wf->reviewAct(person(7),$payrollAct,'approved','Sin competencia'),'Talento Humano sin Nómina no puede aprobar actos');
refuses(fn()=>$wf->reviewAct(person(9),$payrollAct,'approved','Sin competencia'),'El solicitante no aprueba actos');
$wf->reviewAct(person(12),$payrollAct,'approved','Documento y horas verificados por Nómina');
$reviewed=row('SELECT * FROM acts WHERE id=?',[$payrollAct]);
check($reviewed['status']==='approved' && (int)$reviewed['reviewed_by']===12 && $reviewed['reviewed_at']!==null,'La aprobación de Nómina conserva actor, fecha, hora y observación');
refuses(fn()=>$wf->reviewAct(person(12),$payrollAct,'approved','Repetido'),'Un acto no admite doble aprobación');
$ownPayrollAct=insert('acts',['user_id'=>12,'number'=>'PROPIO-NOMINA','issued_on'=>$today,'expires_on'=>'2027-12-31','granted_minutes'=>510,'created_at'=>now()]);
refuses(fn()=>$wf->reviewAct(person(12),$ownPayrollAct,'approved','Propio'),'Nómina no puede aprobar su propio acto');

function specialInput(string $date,string $mode='full'):array {
    return ['date'=>$date,'mode'=>$mode,'special'=>1,'start'=>'22:00','end'=>'06:30','start_day'=>0,'end_day'=>1,'periods'=>[['start'=>'22:00','start_day'=>0,'end'=>'02:00','end_day'=>1],['start'=>'02:30','start_day'=>1,'end'=>'06:30','end_day'=>1]]];
}
$night=$cal->slots([specialInput('2026-12-05')],new DateTimeImmutable($today))[0];
check($night['minutes']===480 && $night['end_at']==='2026-12-06 06:30:00','Jornada especial en sábado cruza medianoche y excluye descanso');
check($wf->consumedSlot($night,'2026-12-06 03:00:00')===270,'Cancelación nocturna calcula el tiempo real disfrutado antes y después de medianoche');
$semesterNight=$cal->slots([specialInput('2027-06-30')],new DateTimeImmutable('2027-01-01'))[0];
check($wf->slotSemesters($semesterNight)===['2027-1'=>120,'2027-2'=>360],'Un turno nocturno distribuye el cupo por las fechas efectivas al cruzar de semestre');
check($wf->slotSemesters($semesterNight,'2027-07-01 03:00:00')===['2027-1'=>120,'2027-2'=>150],'La cancelación parcial de un turno conserva el semestre real de los minutos disfrutados');
$holidayNight=$cal->slots([specialInput('2026-12-08')],new DateTimeImmutable($today));
check($holidayNight[0]['minutes']===480,'Un horario especial permite laborar y solicitar en festivo');
$longNight=specialInput('2026-12-05');$longNight['periods'][1]['end']='07:00';
check($cal->slots([$longNight],new DateTimeImmutable($today))[0]['minutes']===510,'Jornada especial admite exactamente 8,5 horas');
$tooLong=$longNight;$tooLong['periods'][1]['end']='07:01';
refuses(fn()=>$cal->slots([$tooLong],new DateTimeImmutable($today)),'Jornada especial rechaza más de 8,5 horas');
$tooShort=specialInput('2026-12-05');$tooShort['periods'][1]['end']='06:29';
refuses(fn()=>$cal->slots([$tooShort],new DateTimeImmutable($today)),'Jornada especial rechaza menos de 8 horas');
$badNight=specialInput('2026-12-05');$badNight['periods'][1]['start']='01:30';
refuses(fn()=>$cal->slots([$badNight],new DateTimeImmutable($today)),'Rechaza franjas nocturnas superpuestas');
refuses(fn()=>$cal->slots([specialInput('2026-12-05'),['date'=>'2026-12-06','special'=>1,'mode'=>'hours','start'=>'05:00','end'=>'06:00','periods'=>[['start'=>'00:00','end'=>'08:00','start_day'=>0,'end_day'=>0]]]],new DateTimeImmutable($today)),'Detecta cruces de solicitudes entre fechas por turnos nocturnos');

run("UPDATE categories SET supports_required=0 WHERE slug IN ('cumpleanos','academico')");
$birthday=['category_id'=>cat('cumpleanos')['id'],'birthday_year'=>'2026','justification'=>'Disfrute del beneficio anual de cumpleaños','special_note'=>'Mi jornada habitual es nocturna con un descanso de treinta minutos','slots'=>[specialInput('2026-12-05')]];
$birthdayId=$wf->submit(person(9),$birthday,[],null,new DateTimeImmutable($today.' 06:00'));
check((int)row('SELECT birthday_year FROM requests WHERE id=?',[$birthdayId])['birthday_year']===2026,'Cumpleaños registra la vigencia y admite una jornada especial completa');
$duplicateBirthday=$birthday;$duplicateBirthday['slots']=[['date'=>'2026-12-07','mode'=>'full']];
refuses(fn()=>$wf->submit(person(9),$duplicateBirthday,[],null,new DateTimeImmutable($today.' 06:00')),'Una solicitud pendiente reserva el único cumpleaños de la vigencia');
$overlap=['category_id'=>cat('academico')['id'],'justification'=>'Solicitud cruzada con turno nocturno','special_note'=>'Jornada de ocho horas en domingo','slots'=>[['date'=>'2026-12-06','special'=>1,'mode'=>'hours','start'=>'05:00','end'=>'06:00','periods'=>[['start'=>'00:00','end'=>'08:00','start_day'=>0,'end_day'=>0]]]]];
refuses(fn()=>$wf->submit(person(9),$overlap,[],null,new DateTimeImmutable($today.' 06:00')),'Detecta cruces con otra solicitud activa que comenzó el día anterior');
$wf->cancel(person(9),$birthdayId,'Cancela antes de disfrutar el beneficio');
$birthdayId=$wf->submit(person(9),$duplicateBirthday,[],null,new DateTimeImmutable($today.' 06:00'));
check(row('SELECT status FROM requests WHERE id=?',[$birthdayId])['status']==='pending','Cancelar antes de aprobación libera el beneficio anual');
$wf->review(person(11),$birthdayId,'approved','Jornada correcta');$wf->review(person(13),$birthdayId,'approved','Autorizado');
$wf->cancel(person(9),$birthdayId,'Cancelar antes de la jornada');
// El subsecretario que recibió la cancelación la conserva tras un relevo.
$wf->reviewCancellation(person(13),$birthdayId,true,'Cancela sin tiempo disfrutado',new DateTimeImmutable('2026-12-06 10:00'));
$carry=$duplicateBirthday;$carry['slots']=[['date'=>'2027-01-29','mode'=>'full']];
$carryId=$wf->submit(person(9),$carry,[],null,new DateTimeImmutable($today.' 06:00'));
check((int)row('SELECT birthday_year FROM requests WHERE id=?',[$carryId])['birthday_year']===2026,'Nacimiento en diciembre permite disfrutar la vigencia anterior hasta el 30 de enero');
$year2027=$duplicateBirthday;$year2027['birthday_year']='2027';$year2027['slots']=[['date'=>'2027-01-28','mode'=>'full']];
$year2027Id=$wf->submit(person(9),$year2027,[],null,new DateTimeImmutable('2027-01-04 06:00'));
check((int)row('SELECT birthday_year FROM requests WHERE id=?',[$year2027Id])['birthday_year']===2027,'El traslado de diciembre no consume el beneficio de la nueva vigencia');
$late=$birthday;$late['slots']=[specialInput('2027-01-31')];
refuses(fn()=>$wf->submit(person(9),$late,[],null,new DateTimeImmutable($today.' 06:00')),'El traslado de cumpleaños no admite el 31 de enero');
refuses(fn()=>$wf->submit(person(14),$carry,[],null,new DateTimeImmutable($today.' 06:00')),'Un nacimiento distinto de diciembre no traslada el beneficio al siguiente año');
$partial=$duplicateBirthday;$partial['slots']=[slot('2026-12-09','07:30','08:30')];
refuses(fn()=>$wf->submit(person(14),$partial,[],null,new DateTimeImmutable($today.' 06:00')),'Cumpleaños no se solicita por horas');
run('UPDATE users SET birth_date=NULL WHERE id=14');
refuses(fn()=>$wf->submit(person(14),$duplicateBirthday,[],null,new DateTimeImmutable($today.' 06:00')),'No se valida Cumpleaños sin fecha de nacimiento');
run("UPDATE users SET birth_date='1988-06-20' WHERE id=14");
$partialId=$wf->submit(person(14),$duplicateBirthday,[],null,new DateTimeImmutable($today.' 06:00'));
$wf->review(person(11),$partialId,'approved','Jefe autoriza');$wf->review(person(13),$partialId,'approved','Subsecretario autoriza');$wf->cancel(person(14),$partialId,'Cancela parcialmente el día');
$wf->reviewCancellation(person(13),$partialId,true,'Se disfrutó parte de la jornada',new DateTimeImmutable('2026-12-07 08:30'));
$another=$duplicateBirthday;$another['slots']=[['date'=>'2026-12-09','mode'=>'full']];
refuses(fn()=>$wf->submit(person(14),$another,[],null,new DateTimeImmutable($today.' 06:00')),'Una jornada de cumpleaños parcialmente disfrutada conserva consumido el beneficio');
check((int)row('SELECT COUNT(*) n FROM balance_snapshots WHERE user_id=9')['n']>=8,'Los cambios de saldo conservan instantáneas históricas inmutables');

// Restaurar las designaciones institucionales para las pruebas HTTP del directorio original.
run("UPDATE designations SET start_date=? WHERE role='subsecretary' AND user_id=13",[$tomorrow]);
run("UPDATE designations SET start_date=?,end_date=NULL WHERE role='subsecretary' AND user_id=3",[$today]);
run("UPDATE designations SET end_date=? WHERE role='subsecretary' AND user_id=3",[$today]);
run("UPDATE categories SET supports_required=1 WHERE slug IN ('cumpleanos','academico')");
