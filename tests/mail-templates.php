<?php
declare(strict_types=1);
function templatePayload(array $item):array {$item['recipient']='simulacion@correo-pruebas.co';return notificationPayload($item);}
check(count(mailTemplateDefaults())===7,'Siete plantillas de los eventos solicitados');
refuses(fn()=>saveMailTemplate(person(1),'request_registered','Asunto','Texto'),'Funcionario no puede editar plantillas');
refuses(fn()=>saveMailTemplate(person(5),'otro','Asunto','Texto'),'Rechaza eventos no disponibles');
refuses(fn()=>saveMailTemplate(person(5),'request_registered',"Asunto\r\nBcc: otro@correo.co",'Texto'),'Rechaza cabeceras inyectadas');
refuses(fn()=>saveMailTemplate(person(5),'act_approved','Asunto {radicado}','Texto'),'Rechaza variables de otro evento');
refuses(fn()=>saveMailTemplate(person(5),'request_registered','Asunto',''),'Rechaza mensajes vacíos');
saveMailTemplate(person(5),'request_registered','Registro {radicado}','Mensaje personalizado <script>alert(1)</script> {radicado} {enlace}');
notifyTemplate(1,'request_registered',['{radicado}'=>'TH-000123']);
$queued=row('SELECT * FROM outbox ORDER BY id DESC LIMIT 1');$payload=templatePayload($queued);
check($payload['subject']==='Registro TH-000123'&&str_contains($payload['textContent'],'Mensaje personalizado'),'Usa asunto y mensaje personalizado al encolar');
check(!str_contains($payload['htmlContent'],'<script>')&&str_contains($payload['htmlContent'],'&lt;script&gt;'),'Escapa HTML de las plantillas');
saveMailTemplate(person(5),'request_registered','Otro registro','Nuevo texto');
check(templatePayload($queued)===$payload,'Los mensajes en cola conservan la plantilla original');
run('DELETE FROM settings WHERE `key`=?',['mail_template_request_registered']);
function lastTemplateEvent(): string {return json_decode(row('SELECT body FROM outbox WHERE recipient=? ORDER BY id DESC LIMIT 1',['qa1@ejemplo.invalid'])['body'],true)['event']??'';}
$request=testSubmit(1,'ordinario',[slot('2026-11-10','07:30','10:30')]);
check(lastTemplateEvent()==='request_registered','Registro de solicitud genera su plantilla');
$wf->review(person(2),$request,'approved','');check(lastTemplateEvent()==='manager_approved','Jefe intermedio genera plantilla de aprobación del jefe');
$wf->review(person(3),$request,'approved','');check(lastTemplateEvent()==='request_approved','Última etapa genera plantilla de aprobación final');
$single=testSubmit(1,'ordinario',[slot('2026-11-11','07:30','08:30')]);$wf->review(person(2),$single,'approved','');
check(lastTemplateEvent()==='request_approved','Permiso de hasta dos horas notifica directamente aprobación final');
$returned=testSubmit(1,'ordinario',[slot('2026-11-12','07:30','08:30')]);$wf->review(person(2),$returned,'returned','Observación privada de devolución');
check(lastTemplateEvent()==='request_returned'&&!str_contains(templatePayload(row('SELECT * FROM outbox WHERE recipient=? ORDER BY id DESC LIMIT 1',['qa1@ejemplo.invalid']))['textContent'],'Observación privada'),'Devolución usa plantilla sin divulgar comentarios');
$rejected=testSubmit(1,'ordinario',[slot('2026-11-13','07:00','08:00')]);$wf->review(person(2),$rejected,'rejected','Observación privada de rechazo');
check(lastTemplateEvent()==='request_rejected','Rechazo de solicitud usa su plantilla');
foreach(['approved','rejected'] as $decision){
 $act=insert('acts',['user_id'=>1,'number'=>'R-plantilla-'.$decision,'issued_on'=>date('Y-m-d'),'expires_on'=>'2027-12-31','granted_minutes'=>510,'created_at'=>now()]);
 $wf->reviewAct(person(5),$act,$decision,'Observación privada de Nómina');
 check(lastTemplateEvent()==='act_'.$decision,'Decisión de Nómina usa plantilla '.$decision);
}
check((int)row("SELECT COUNT(*) n FROM events WHERE action='Plantilla de correo actualizada'")['n']===2,'Audita los cambios de plantillas');
