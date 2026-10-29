<?php
declare(strict_types=1);
seedJobCatalog(db());
$before=(int)row('SELECT COUNT(*) n FROM job_grades')['n'];seedJobCatalog(db());
check((int)row('SELECT COUNT(*) n FROM job_grades')['n']===$before,'Migración de cargos conserva datos y no duplica grados');
saveJob(person(5),['title'=>'Profesional','code'=>'00219','grade'=>'01']);
$job=(int)row('SELECT id FROM jobs WHERE title=?',['Profesional'])['id'];
saveJobGrade(person(5),['job_id'=>$job,'grade'=>'24']);
check(count(rows('SELECT * FROM job_grades WHERE job_id=?',[$job]))===2,'Un cargo tiene un código y varios grados');
check(resolveJob(['job_title'=>'Profesional','code'=>'00219','grade'=>'01'])['code']==='00219','El catálogo conserva ceros iniciales en códigos y grados');
refuses(fn()=>saveJob(person(5),['title'=>'profesional','code'=>'999','grade'=>'02']),'No admite el mismo cargo con otro código ni diferencias de mayúsculas');
refuses(fn()=>saveJobGrade(person(5),['job_id'=>$job,'grade'=>'24']),'No repite un grado del mismo cargo');
refuses(fn()=>saveJob(person(8),['title'=>'No autorizado','code'=>'1','grade'=>'1']),'Solicitante no administra el catálogo');
refuses(fn()=>resolveJob(['job_title'=>'Profesional','code'=>'999','grade'=>'24']),'Servidor rechaza códigos manipulados');
refuses(fn()=>resolveJob(['job_title'=>'Profesional','code'=>'00219','grade'=>'99']),'Servidor rechaza grados ajenos al cargo');
$employee=person(8);$snapshot=row('SELECT snapshot FROM requests WHERE user_id=8 LIMIT 1');
saveUser(person(5),array_replace($employee,['job_title'=>'Profesional','code'=>'00219','grade'=>'01','roles'=>roles($employee)]),8);
check(person(8)['code']==='00219' && person(8)['grade']==='01','Edición de funcionario toma una combinación registrada');
refuses(fn()=>saveUser(person(5),array_replace(person(8),['code'=>'ERROR','roles'=>[]]),8),'No guarda un funcionario con combinación inválida');
saveJob(person(5),['job_id'=>$job,'title'=>'Profesional','code'=>'219']);
check(person(8)['code']==='219' && row('SELECT snapshot FROM requests WHERE user_id=8 LIMIT 1')===$snapshot,'Editar el catálogo actualiza el funcionario y preserva las solicitudes históricas');
refuses(fn()=>commitImport(person(5),[['cedula'=>'INVALID-CATALOG','nombre'=>'Dato inválido','usuario'=>'invalid.catalog','correo'=>'x@ejemplo.invalid','cargo'=>'No existe','codigo'=>'1','grado'=>'1','dependencia'=>'Dependencia de prueba','cedula_jefe'=>'']]),'Excel exige cargos y grados previamente registrados');
check(row('SELECT id FROM users WHERE identification=?',['INVALID-CATALOG'])===null,'Una importación inválida no deja registros parciales');

// Mismos rechazos por la ruta individual y masiva, en ambas etapas, y continuidad del lote.
run("UPDATE categories SET supports_required=0 WHERE slug='academico'");
$bad=$wf->submit(person(8),['category_id'=>cat('academico')['id'],'justification'=>'Solicitud inválida para verificar el proceso','slots'=>[slot('2026-12-09','13:00','14:00')]],[],null,new DateTimeImmutable('2026-10-26'));
$good=$wf->submit(person(8),['category_id'=>cat('academico')['id'],'justification'=>'Solicitud válida posterior dentro del mismo lote','slots'=>[slot('2026-12-09','14:00','15:00')]],[],null,new DateTimeImmutable('2026-10-26'));
$manager=(int)row('SELECT assigned_user_id FROM approvals WHERE id=?',[approvalId($bad)])['assigned_user_id'];
run('UPDATE request_slots SET start_at=?,date=? WHERE request_id=?',['2020-01-02 13:00:00','2020-01-02',$bad]);
try{$wf->review(person($manager),$bad,'approved','');throw new RuntimeException('Aceptó fecha pasada');}catch(DomainException $e){$manualError=$e->getMessage();}
$beforeEvents=(int)row('SELECT COUNT(*) n FROM events WHERE request_id=?',[$bad])['n'];
$batch=$wf->approveMany(person($manager),[approvalId($bad),approvalId($good)]);
check(count($batch['approved'])===1 && $batch['failed'][0]['message']===$manualError,'Lote aplica el mismo rechazo individual y continúa con la siguiente solicitud');
check(row('SELECT status FROM approvals WHERE id=?',[approvalId($bad)])['status']==='waiting' && (int)row('SELECT COUNT(*) n FROM events WHERE request_id=?',[$bad])['n']===$beforeEvents,'Fallo no deja aprobación ni eventos parciales');
run("UPDATE categories SET supports_required=1 WHERE slug='academico'");
$next=row("SELECT * FROM approvals WHERE request_id=? AND status='waiting' ORDER BY step LIMIT 1",[$good]);
try{$wf->review(person((int)$next['assigned_user_id']),$good,'approved','');throw new RuntimeException('Aceptó soporte faltante');}catch(DomainException $e){$manualError=$e->getMessage();}
$failed=$wf->approveMany(person((int)$next['assigned_user_id']),[(int)$next['id']]);
check($failed['failed'][0]['message']===$manualError && str_contains($manualError,'soporte'),'Validación del soporte se comparte en la etapa final y masiva');
// Estas solicitudes sintéticas ya no participan en pruebas HTTP posteriores.
$wf->review(person($manager),$bad,'rejected','Solicitud sintética terminada');
$wf->review(person((int)$next['assigned_user_id']),$good,'rejected','Solicitud sintética terminada');
