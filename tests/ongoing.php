<?php
// Se ejecuta únicamente en la base aislada de core.php.
(function() use($wf) {
    $category=cat('academico');run('UPDATE categories SET supports_required=0 WHERE id=?',[$category['id']]);
    $input=['category_id'=>$category['id'],'justification'=>'Solicitud urgente de prueba durante el disfrute','slots'=>[slot('2026-11-10','07:30','17:00')]];
    refuses(fn()=>$wf->submit(person(8),$input,[],null,new DateTimeImmutable('2026-11-10 10:00')),'Registro ya iniciado exige motivo de urgencia');
    $input['exception_reason']='Urgencia sobrevenida durante la jornada laboral';
    $urgent=$wf->submit(person(8),$input,[],null,new DateTimeImmutable('2026-11-10 10:00'));
    check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$urgent])['total_minutes']===510,'Registro urgente conserva toda la duración y las fechas originales');
    check(row("SELECT id FROM events WHERE request_id=? AND action='Registro urgente durante el disfrute'",[$urgent])!==null,'Registro urgente deja constancia con su motivo');
    refuses(fn()=>$wf->calendar->slots($input['slots'],new DateTimeImmutable('2026-11-10 17:00')),'El instante final ya cuenta como periodo terminado');
    refuses(fn()=>$wf->submit(person(8),$input,[],null,new DateTimeImmutable('2026-11-11 08:00')),'No registra un periodo completamente terminado aunque se justifique');
    check($wf->periodState($urgent,new DateTimeImmutable('2026-11-10 07:30'))==='ongoing','El instante inicial ya exige aprobación excepcional');
    check($wf->periodState($urgent,new DateTimeImmutable('2026-11-10 17:00'))==='ended','La clasificación de aprobaciones bloquea el instante final');
    $now=new DateTimeImmutable();$from=$now->modify('-1 hour');$to=$now->modify('+1 hour');
    run('UPDATE request_slots SET start_at=?,end_at=? WHERE request_id=?',[$from->format('Y-m-d H:i:s'),$to->format('Y-m-d H:i:s'),$urgent]);
    foreach($wf->route(person(8),$category,510) as $i=>$route) run('UPDATE approvals SET assigned_user_id=?,designation_id=? WHERE request_id=? AND step=?',[$route['assigned_user_id'],$route['designation_id'],$urgent,$i+1]);
    $stage=row('SELECT * FROM approvals WHERE request_id=? ORDER BY step LIMIT 1',[$urgent]);$manager=person((int)$stage['assigned_user_id']);
    refuses(fn()=>$wf->review($manager,$urgent,'approved',''),'Aprobador no puede aprobar en curso sin justificación propia');
    $wf->review($manager,$urgent,'approved','Datos verificados','Olvido de revisión mientras se atendía una emergencia');
    check(row('SELECT status FROM requests WHERE id=?',[$urgent])['status']==='pending','Excepción del jefe conserva la etapa del subsecretario');
    $next=row("SELECT * FROM approvals WHERE request_id=? AND status='waiting'",[$urgent]);$sub=person((int)$next['assigned_user_id']);
    $failure=$wf->approveMany($sub,[(int)$next['id']]);
    check(count($failure['failed'])===1,'La etapa final masiva también exige justificación propia');
    $batch=$wf->approveMany($sub,[(int)$next['id']],'Revisado',[$urgent=>'Urgencia comprobada y soportes revisados durante el disfrute']);
    check(count($batch['approved'])===1 && row('SELECT status FROM requests WHERE id=?',[$urgent])['status']==='approved','Excepción masiva concluye la ruta con motivo individual: '.json_encode($batch,JSON_UNESCAPED_UNICODE));
    check(str_contains(row('SELECT comment FROM approvals WHERE id=?',[$next['id']])['comment'],'Aprobación excepcional durante el disfrute'),'Constancia de aprobación incluye marca excepcional y motivo');
    $input['slots']=[slot('2026-11-12','07:30','17:00')];$ended=$wf->submit(person(8),$input,[],null,new DateTimeImmutable(date('Y-m-d').' 06:00'));
    run('UPDATE request_slots SET start_at=?,end_at=? WHERE request_id=?',[$now->modify('-2 hours')->format('Y-m-d H:i:s'),$now->modify('-1 second')->format('Y-m-d H:i:s'),$ended]);
    $endStage=row('SELECT * FROM approvals WHERE request_id=? ORDER BY step LIMIT 1',[$ended]);$endActor=person((int)$endStage['assigned_user_id']);
    refuses(fn()=>$wf->review($endActor,$ended,'approved','','Motivo largo que no autoriza periodos terminados'),'Ni una justificación permite aprobar periodos terminados');
    check(count($wf->approveMany($endActor,[(int)$endStage['id']],'',[$ended=>'Motivo que tampoco permite el periodo terminado'])['failed'])===1,'Masiva bloquea también el periodo terminado');
    $wf->review($endActor,$ended,'rejected','Cierre de la prueba aislada');
    run('UPDATE categories SET supports_required=? WHERE id=?',[$category['supports_required'],$category['id']]);
    $fixture=testSubmit(8,'academico',[slot('2026-12-07','15:00','15:30')]);
    run('UPDATE request_slots SET start_at=?,end_at=? WHERE request_id=?',[$from->format('Y-m-d H:i:s'),$now->modify('+2 hours')->format('Y-m-d H:i:s'),$fixture]);
    $fixtureStage=row('SELECT * FROM approvals WHERE request_id=? ORDER BY step LIMIT 1',[$fixture]);
    file_put_contents(ROOT.'/.work/ongoing-http.json',json_encode(['id'=>$fixture,'stage'=>$fixtureStage['id']]));
})();
