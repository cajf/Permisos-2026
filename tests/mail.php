<?php
declare(strict_types=1);
$GLOBALS['config']['mail']=['enabled'=>false,'api_key'=>'','from'=>'','from_name'=>'Prueba','start_at'=>'','daily_limit'=>250];
$old=insert('outbox',['recipient'=>'prueba@correo-pruebas.co','subject'=>'Histórico','body'=>'Dato privado que no debe salir','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day'))]);
$id=insert('outbox',['recipient'=>'prueba@correo-pruebas.co','subject'=>'Solicitud TH-123','body'=>'Comentario privado de salud y cédula','created_at'=>now()]);
$calls=0;$sent=[];$fake=function($payload)use(&$calls,&$sent){$calls++;$sent[]=$payload;return 'aceptacion-simulada-'.$calls;};
check(count(notificationMailIssues())>=3,'Configuración desactivada informa datos faltantes sin exponer claves');
processNotificationMail(5,$fake);check($calls===0&&row('SELECT status FROM outbox WHERE id=?',[$id])['status']==='pending','No envía ni cambia cola sin configurar');
$GLOBALS['config']['mail']=['enabled'=>true,'api_key'=>'clave-ficticia-sin-red','from'=>'notificaciones@correo-pruebas.co','from_name'=>'Prueba','start_at'=>date('Y-m-d').' 00:00:00','daily_limit'=>250];
check(notificationMailIssues()===[],'Configuración completa permite procesar la cola');
$r=processNotificationMail(5,$fake);check($r['accepted']===1&&$calls===1,'Respuesta simulada aceptada actualiza el estado');
check(row('SELECT status FROM outbox WHERE id=?',[$old])['status']==='pending','Los mensajes anteriores a la activación permanecen sin enviar');
check(!str_contains(json_encode($sent),'Comentario privado')&&!isset($sent[0]['attachment']),'No transmite motivos, comentarios ni soportes');
check(isset($sent[0]['sender'],$sent[0]['to'],$sent[0]['htmlContent'],$sent[0]['textContent']),'Carga de API contiene remitente, destinatario y contenido');
check(str_contains(setting('mail_receipt_'.$id),'aceptacion-simulada-1'),'Guarda identificador de aceptación sin cambiar esquema');
processNotificationMail(5,$fake);check($calls===1,'No reenvía mensajes aceptados');
$invalid=insert('outbox',['recipient'=>'qa@ejemplo.invalid','subject'=>'Demostración','body'=>'','created_at'=>now()]);
processNotificationMail(5,$fake);check($calls===1&&row('SELECT status FROM outbox WHERE id=?',[$invalid])['status']==='blocked','Destinatarios ficticios bloqueados sin llamada externa');
foreach(['a@example.com','a@ejemplo.test','correo inválido'] as $address)check(!notificationMailbox($address),'Descarta dirección de prueba: '.$address);
$uncertain=insert('outbox',['recipient'=>'otro@correo-pruebas.co','subject'=>'Tiempo agotado simulado','body'=>'','created_at'=>now()]);
$r=processNotificationMail(5,function(){throw new NotificationDeliveryError('No confirmado','uncertain');});
check($r['uncertain']===1&&row('SELECT status FROM outbox WHERE id=?',[$uncertain])['status']==='uncertain','Respuesta ambigua no se registra como enviada');
setSetting('mail_pause_until','0');processNotificationMail(5,$fake);check($calls===1,'No reintenta respuesta ambigua automáticamente');
$retry=insert('outbox',['recipient'=>'otro@correo-pruebas.co','subject'=>'Límite simulado','body'=>'','created_at'=>now()]);
$r=processNotificationMail(5,function(){throw new NotificationDeliveryError('HTTP 429 simulado','failed');});
check($r['failed']===1&&row('SELECT status FROM outbox WHERE id=?',[$retry])['status']==='failed','Límite API deja reintento pendiente');
$r=processNotificationMail(5,$fake);check($r['paused']&&$calls===1,'Pausa protege frente a reintentos inmediatos');
setSetting('mail_pause_until','0');processNotificationMail(5,$fake);check($calls===2,'Reintenta rechazo temporal después de la pausa');
$GLOBALS['config']['mail']['daily_limit']=1;
insert('outbox',['recipient'=>'tercero@correo-pruebas.co','subject'=>'Cupo local','body'=>'','created_at'=>now()]);
$r=processNotificationMail(5,$fake);check($r['paused']&&$calls===2,'Cupo local limita llamadas incluso con mensajes pendientes');
$GLOBALS['config']['mail']['daily_limit']=250;
$lock='th-mail-'.substr(hash('sha256',databaseIdentity()),0,40);$other=mysqlConnection($GLOBALS['config']['mysql']);$s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([$lock]);
$before=$calls;processNotificationMail(5,$fake);check($calls===$before,'Bloqueo MySQL evita trabajadores simultáneos');$s=$other->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);
$GLOBALS['config']['mail']['api_key']="clave\r\ninyectada";check(notificationMailIssues()!==[],'Rechaza inyección de cabeceras en la clave');
$GLOBALS['config']['mail']['enabled']=false;
check(notificationBrevoResponse('{"messageId":"id-simulado"}',201)==='id-simulado','Interpreta confirmación real de formato Brevo');
foreach([[false,0,'uncertain'],['respuesta inválida',201,'uncertain'],['{}',500,'uncertain'],['{}',429,'failed'],['{"api-key":"secreto"}',401,'blocked']] as [$body,$code,$state]){
 try{notificationBrevoResponse($body,$code);check(false,'Debe rechazar la respuesta simulada');}catch(NotificationDeliveryError $e){check($e->deliveryState===$state&&!str_contains($e->getMessage(),'secreto'),'Clasifica respuesta '.$code.' sin revelar respuesta privada');}
}
require __DIR__.'/mail-templates.php';
file_put_contents(ROOT.'/.work/mail-test-result.json',json_encode(['database_config'=>$GLOBALS['test_configuration_file'],'checks'=>$checks]));
echo $checks." comprobaciones de correo correctas, sin envíos reales.\n";
