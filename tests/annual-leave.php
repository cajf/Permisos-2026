<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Admin.php';
installMysqlSchema(db());
insert('departments',['name'=>'Dependencia de prueba']);
foreach (['[]','["manager"]','["subsecretary"]','["admin"]'] as $index=>$roles) {
    $id=$index+1;
    fixtureUser(['name'=>"Persona $id",'identification'=>"PR-$id",'username'=>"pr$id",'email'=>"pr$id@example.invalid",'password'=>'unused','job_title'=>'Prueba','department_id'=>1,'roles'=>$roles,'active'=>1,'force_password'=>0]);
}
run("UPDATE person_employments SET manager_id=2 WHERE person_id=1");
setSetting('subsecretary_id','3');
foreach (require ROOT.'/database/catalog.php' as $category) {
    insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$category)+['supports_required'=>0]);
}
run("UPDATE persons SET birth_date='1990-12-12' WHERE id=1");
$employee=row("SELECT * FROM user_profiles WHERE id=1");
$admin=row("SELECT * FROM user_profiles WHERE id=4");
$workflow=new Workflow();
$clock=new DateTimeImmutable('2026-11-09 08:00:00');
$categories=array_column(rows('SELECT * FROM categories'),null,'slug');
$checks=0;

function verify(bool $ok,string $description):void {
    global $checks;
    if (!$ok) throw new RuntimeException($description);
    $checks++;
    echo "OK $description\n";
}
function blocked(callable $action,string $message):void {
    try {$action();}
    catch (DomainException $error) {verify(str_contains($error->getMessage(),$message),$error->getMessage());return;}
    throw new RuntimeException("Se esperaba el bloqueo: $message");
}
function registerDays(string $type,array $dates,array $fields=[],?int $edit=null,?DateTimeImmutable $time=null):int {
    global $workflow,$employee,$categories,$clock;
    return $workflow->submit($employee,$fields+[
        'category_id'=>$categories[$type]['id'],
        'justification'=>'Solicitud aislada de prueba funcional',
        'slots'=>array_map(fn($date)=>['date'=>$date,'mode'=>'full'],$dates),
    ],[],$edit,$time??$clock);
}

foreach (['bienestar'] as $type) {
    $dates=['2026-12-01','2026-12-02','2026-12-03'];
    AnnualLeave::saveLimit($type,2026,'2');
    blocked(fn()=>registerDays($type,$dates),'quedan 2 día(s)');
    $request=registerDays($type,array_slice($dates,0,2));
    blocked(fn()=>registerDays($type,[$dates[2]]),'quedan 0 día(s)');
    run("UPDATE requests SET status='returned' WHERE id=?",[$request]);
    verify(registerDays($type,array_slice($dates,0,2),[],$request)===$request,'La corrección conserva su cupo disponible');
    run("UPDATE requests SET status='cancelled',cancelled_at=? WHERE id=?",[$dates[0].' 08:00:00',$request]);
    verify(AnnualLeave::remaining($workflow,1,$categories[$type],2026)===1,'Una cancelación parcial solo libera las fechas sin disfrute');
    registerDays($type,[$dates[2]]);
    AnnualLeave::saveLimit($type,2026,'');
    verify(AnnualLeave::remaining($workflow,1,$categories[$type],2026)===null,'Vacío elimina el límite de la vigencia');
}
insert('holidays',['date'=>'2026-12-07','name'=>'Día no laborable de prueba de Quinquenio']);
$quinquenioDates=['2026-12-04','2026-12-08','2026-12-09','2026-12-10'];
foreach ([false,true] as $officialWorker) {
    $generated=(new WorkCalendar($officialWorker))->consecutiveLeaveSlots('quinquenio',$quinquenioDates[0],$clock);
    verify(array_column($generated,'date')===$quinquenioDates,'Quinquenio genera cuatro jornadas y omite fines de semana y días no laborales');
    verify(array_sum(array_column($generated,'minutes'))===4*($officialWorker?480:510),'Quinquenio respeta la duración diaria del tipo de vinculación');
}
$changed=(new WorkCalendar())->consecutiveLeaveSlots('quinquenio','2026-12-08',$clock);
verify(array_column($changed,'date')===['2026-12-08','2026-12-09','2026-12-10','2026-12-11'],'Al cambiar la primera fecha se recalculan las tres siguientes');
AnnualLeave::saveLimit('quinquenio',2026,'3');
blocked(fn()=>registerDays('quinquenio',[$quinquenioDates[0]]),'quedan 3 día(s)');
AnnualLeave::saveLimit('quinquenio',2026,'4');
$request=registerDays('quinquenio',[$quinquenioDates[0],'2026-12-31']);
verify(array_column(rows('SELECT date FROM request_slots WHERE request_id=? ORDER BY date',[$request]),'date')===$quinquenioDates,'El servidor calcula las cuatro fechas e ignora fechas adicionales manipuladas');
blocked(fn()=>registerDays('quinquenio',['2026-12-21']),'quedan 0 día(s)');
blocked(fn()=>registerDays('bienestar',[$quinquenioDates[2]]),'se cruza');
run("UPDATE requests SET status='returned' WHERE id=?",[$request]);
verify(registerDays('quinquenio',[$quinquenioDates[0]],[],$request)===$request,'La corrección de Quinquenio conserva el cupo de sus cuatro jornadas');
run("UPDATE requests SET status='cancelled',cancelled_at=? WHERE id=?",[$quinquenioDates[0].' 08:00:00',$request]);
verify(AnnualLeave::remaining($workflow,1,$categories['quinquenio'],2026)===3,'La cancelación parcial libera las tres jornadas de Quinquenio sin disfrute');
blocked(fn()=>AnnualLeave::saveLimit('bienestar',2026,'1.5'),'número entero');
blocked(fn()=>registerDays('bienestar',['2027-01-04']),'vigencia 2026');
blocked(fn()=>registerDays('bienestar',[],['slots'=>[['date'=>'2026-12-04','mode'=>'hours','start'=>'08:00','end'=>'09:00']]]),'Día completo');

verify(AnnualLeave::limit('cumpleanos',2026)===1,'Se conserva el día de Cumpleaños por defecto');
AnnualLeave::saveLimit('cumpleanos',2026,'2');
registerDays('cumpleanos',['2026-12-29']);
$january=new DateTimeImmutable('2027-01-04 07:00:00');
registerDays('cumpleanos',['2027-01-08'],['birthday_year'=>'2026'],null,$january);
blocked(fn()=>registerDays('cumpleanos',['2027-01-12'],['birthday_year'=>'2026'],null,$january),'quedan 0 día(s)');
verify(AnnualLeave::remaining($workflow,1,$categories['cumpleanos'],2027)===1,'El disfrute diferido no descuenta el cupo de la nueva vigencia');

$break=['start_date'=>'2026-12-14','end_date'=>'2026-12-20'];
blocked(fn()=>saveSchoolBreak($employee,$break,$clock),'permiso');
blocked(fn()=>saveSchoolBreak($admin,['start_date'=>'2026-12-28','end_date'=>'2027-01-03'],$clock),'vigencia 2026');
blocked(fn()=>registerDays('receso',['2026-12-14']),'semana de receso');
saveSchoolBreak($admin,$break,$clock);
insert('holidays',['date'=>'2026-12-16','name'=>'Día no laborable de prueba']);
$expected=['2026-12-14','2026-12-15','2026-12-17','2026-12-18'];
verify((new WorkCalendar())->schoolBreakDays($clock)===$expected && (new WorkCalendar(true))->schoolBreakDays($clock)===$expected,'Las fechas excluyen festivos y fin de semana en ambas jornadas');
$midweek=new DateTimeImmutable('2026-12-15 13:00:00');
$recessDates=(new WorkCalendar())->schoolBreakDates($midweek);
verify($recessDates===array_combine($expected,[false,true,true,true]),'Se distinguen las jornadas terminadas de las vigentes y futuras');
$isRecess=true;
$slots=[['date'=>'2026-12-14','mode'=>'full']];
$u=$employee;
ob_start();
require ROOT.'/views/slot-fields.php';
$renderedDates=ob_get_clean();
verify(str_contains($renderedDates,'value="2026-12-14" disabled data-ended')
    && str_contains($renderedDates,'value="2026-12-15" >15/12/2026')
    && !str_contains($renderedDates,'value="2026-12-16"'),'El selector muestra los días hábiles y bloquea solo los ya finalizados');
$afterRecess=new DateTimeImmutable('2026-12-21 08:00:00');
verify((new WorkCalendar())->schoolBreakDates($afterRecess)===array_fill_keys($expected,false)
    && (new WorkCalendar())->schoolBreakDays($afterRecess)===[],'Un receso vencido conserva las fechas visibles y no admite radicación');
blocked(fn()=>registerDays('receso',['2026-12-21']),'semana de receso');
blocked(fn()=>registerDays('receso',['2026-12-16']),'no es un día laborable');
AnnualLeave::saveLimit('receso',2026,'1');
blocked(fn()=>registerDays('receso',array_slice($expected,0,2)),'quedan 1 día(s)');
$request=registerDays('receso',[$expected[0]]);
blocked(fn()=>registerDays('receso',[$expected[1]]),'quedan 0 día(s)');
run("UPDATE requests SET status='rejected' WHERE id=?",[$request]);
saveSchoolBreak($admin,['start_date'=>'2026-12-21','end_date'=>'2026-12-23'],$clock);
blocked(fn()=>registerDays('receso',[$expected[1]]),'semana de receso');
verify(registerDays('receso',['2026-12-22'])>0,'La edición sustituye las fechas disponibles y el rechazo libera el cupo');
$cancelTest=registerDays('bienestar',['2026-11-23','2026-11-24']);
$boundary=new DateTimeImmutable('2026-11-24 17:00:00');
verify($workflow->periodState($cancelTest,$boundary->modify('-1 second'))==='ongoing','No termina el disfrute mientras quede tiempo en la última jornada');
verify($workflow->periodState($cancelTest,$boundary)==='ended','La solicitud termina exactamente al finalizar la última jornada');
run("UPDATE request_slots SET start_at='2020-01-02 07:30:00',end_at='2020-01-02 17:00:00' WHERE request_id=?",[$cancelTest]);
foreach (['pending','returned','approved'] as $status) {
    run('UPDATE requests SET status=? WHERE id=?',[$status,$cancelTest]);
    blocked(fn()=>$workflow->cancel($employee,$cancelTest,'Cancelación de prueba de un periodo finalizado'),'No se permite cancelar');
    verify(row('SELECT status FROM requests WHERE id=?',[$cancelTest])['status']===$status,'El bloqueo conserva el estado original de la solicitud');
}

echo "$checks comprobaciones correctas en MySQL.\n";
